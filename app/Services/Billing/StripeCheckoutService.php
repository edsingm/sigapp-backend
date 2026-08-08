<?php

namespace App\Services\Billing;

use App\Models\Central\BillingAddon;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonPurchase;
use App\Support\TenantAppUrl;
use App\Traits\LogsAudit;
use Laravel\Cashier\Cashier;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\StripeClient;

class StripeCheckoutService
{
    use LogsAudit;

    public function __construct(
        private readonly ?TenantAppUrl $tenantAppUrl = null,
    ) {}

    protected function stripe(): StripeClient
    {
        return Cashier::stripe();
    }

    /**
     * Cria um cliente no Stripe para o tenant especificado e armazena o ID do cliente.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createCustomer(Tenant $tenant, array $validated): Customer
    {
        $tenantId = (int) $tenant->getKey();
        $tenantSlug = (string) $tenant->getAttribute('slug');

        $customer = $this->stripe()->customers->create([
            'email' => (string) $validated['admin_email'],
            'name' => (string) $validated['organization_name'],
            'metadata' => [
                'tenant_id' => (string) $tenantId,
                'tenant_slug' => $tenantSlug,
            ],
        ]);

        $tenant->update(['stripe_id' => $customer->id]);

        return $customer;
    }

    /**
     * Sincroniza no Customer os dados de contato/endereço usados pelo faturamento.
     * O CPF/CNPJ local permanece canônico e não é alterado por esta operação.
     *
     * @param  array<string, mixed>  $profile
     */
    public function updateCustomerBillingProfile(string $customerId, array $profile): void
    {
        $address = is_array($profile['address'] ?? null) ? $profile['address'] : [];

        $this->stripe()->customers->update($customerId, [
            'name' => $profile['legal_name'] ?? null,
            'email' => $profile['email'] ?? null,
            'phone' => $profile['phone'] ?? null,
            'address' => [
                'line1' => trim((string) ($address['street'] ?? '').', '.(string) ($address['number'] ?? '')),
                'line2' => $address['complement'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'country' => $address['country'] ?? 'BR',
            ],
        ]);
    }

    /**
     * Cria uma sessão de Checkout do Stripe para uma assinatura.
     *
     * Ao omitir `payment_method_types`, o Stripe usa automaticamente todos os métodos
     * habilitados no Dashboard para a moeda BRL (cartão, Boleto, Pix, etc.).
     *
     * @param  array<string, mixed>  $sessionOptions  Opções extras mescladas ao payload da sessão.
     */
    public function createSubscriptionSession(
        Tenant $tenant,
        Plan $plan,
        string $customerId,
        bool $trialEligible,
        array $sessionOptions = [],
    ): Session {
        $priceId = (string) ($plan->getAttribute('stripe_price_id') ?? $this->createPriceOnTheFly($plan));
        $tenantId = (string) $tenant->getKey();
        $planTrialDays = (int) $plan->getAttribute('trial_days');
        $planSlug = (string) $plan->getAttribute('slug');

        // Só concede trial no Stripe se o email for elegível (decisão do trial_ledger).
        // Sem isso, um email repetido ganharia outro trial mesmo com trial_ends_at nulo localmente.
        $subscriptionData = ['metadata' => ['tenant_id' => $tenantId]];
        if ($trialEligible && $planTrialDays > 0) {
            $subscriptionData['trial_period_days'] = $planTrialDays;
        }

        return $this->stripe()->checkout->sessions->create(array_merge([
            'customer' => $customerId,
            'client_reference_id' => $tenantId,
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'subscription_data' => $subscriptionData,
            // Permite códigos de desconto/cupom no checkout
            'allow_promotion_codes' => true,
            // Coleta Tax ID (CNPJ/CPF) e endereço do cliente
            'tax_id_collection' => ['enabled' => true],
            'customer_update' => ['name' => 'auto', 'address' => 'auto'],
            'success_url' => $this->signupSuccessUrl(),
            'cancel_url' => $this->signupCancelUrl($planSlug),
            'metadata' => [
                'tenant_id' => $tenantId,
                'plan_slug' => $planSlug,
            ],
        ], $sessionOptions));
    }

    public function createAddonPaymentSession(
        Tenant $tenant,
        BillingAddon $addon,
        TenantAddonPurchase $purchase,
    ): Session {
        $customerId = $tenant->getAttribute('stripe_id');
        if (! is_string($customerId) || $customerId === '') {
            throw new \InvalidArgumentException('O tenant não possui Customer Stripe para a compra avulsa.');
        }

        $metadata = [
            'purpose' => TenantAddonPurchaseService::CHECKOUT_PURPOSE,
            'purchase_id' => (string) $purchase->getKey(),
            'tenant_id' => (string) $tenant->getKey(),
            'addon_id' => (string) $addon->getKey(),
            'price_id' => (string) $addon->stripe_price_id,
        ];

        return $this->stripe()->checkout->sessions->create([
            'customer' => $customerId,
            'client_reference_id' => (string) $purchase->getKey(),
            'mode' => 'payment',
            'line_items' => [[
                'price' => (string) $addon->stripe_price_id,
                'quantity' => $purchase->quantity,
            ]],
            'allow_promotion_codes' => true,
            'tax_id_collection' => ['enabled' => true],
            'customer_update' => ['name' => 'auto', 'address' => 'auto'],
            'invoice_creation' => ['enabled' => true],
            'payment_intent_data' => ['metadata' => $metadata],
            'metadata' => $metadata,
            'success_url' => $this->tenantAppUrl()->billingUrl($tenant, [
                'billing_tab' => 'addons',
                'addon_checkout' => 'success',
                'session_id' => '{CHECKOUT_SESSION_ID}',
            ]),
            'cancel_url' => $this->tenantAppUrl()->billingUrl($tenant, [
                'billing_tab' => 'addons',
                'addon_checkout' => 'cancelled',
            ]),
        ], [
            'idempotency_key' => 'tenant-addon-purchase-'.$purchase->getKey(),
        ]);
    }

    /**
     * Cria um Produto + Preço no Stripe em tempo de execução quando o plano não possui um stripe_price_id.
     *
     * Usa idempotency keys para evitar criação duplicada em caso de retry.
     */
    public function createPriceOnTheFly(Plan $plan): string
    {
        $priceInCents = (int) round(((float) $plan->getAttribute('price')) * 100);

        $this->audit('tenant.signup_price_created_on_the_fly', 'Plano sem stripe_price_id. Criando price emergencialmente.', [
            'plan_id' => $plan->id,
            'plan_slug' => $plan->slug,
            'price_in_cents' => $priceInCents,
        ]);

        $idempotencyBase = 'plan-'.$plan->id.'-'.$plan->slug;

        $product = $this->stripe()->products->create(
            [
                'name' => (string) $plan->getAttribute('name'),
                'description' => (string) ($plan->getAttribute('description') ?? ''),
            ],
            ['idempotency_key' => 'product-'.$idempotencyBase]
        );

        $price = $this->stripe()->prices->create(
            [
                'product' => $product->id,
                'unit_amount' => $priceInCents,
                'currency' => (string) config('cashier.currency', 'brl'),
                'recurring' => ['interval' => 'month'],
            ],
            ['idempotency_key' => 'price-'.$idempotencyBase.'-'.$priceInCents]
        );

        $plan->update(['stripe_price_id' => $price->id]);

        return $price->id;
    }

    private function signupSuccessUrl(): string
    {
        $landingUrl = config('app.landing_url');

        return rtrim((string) $landingUrl, '/').'/cadastro?success=1&session_id={CHECKOUT_SESSION_ID}';
    }

    private function tenantAppUrl(): TenantAppUrl
    {
        return $this->tenantAppUrl ?? app(TenantAppUrl::class);
    }

    private function signupCancelUrl(string $planSlug): string
    {
        $query = http_build_query(['plan' => $planSlug, 'cancelled' => 1]);

        $landingUrl = config('app.landing_url');

        return rtrim((string) $landingUrl, '/').'/cadastro?'.$query.'&session_id={CHECKOUT_SESSION_ID}';
    }
}
