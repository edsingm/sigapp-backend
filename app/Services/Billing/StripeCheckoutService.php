<?php

namespace App\Services\Billing;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Traits\LogsAudit;
use Laravel\Cashier\Cashier;

class StripeCheckoutService
{
    use LogsAudit;

    /**
     * Cria um cliente no Stripe para o tenant especificado e armazena o ID do cliente.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createCustomer(Tenant $tenant, array $validated): \Stripe\Customer
    {
        $customer = Cashier::stripe()->customers->create([
            'email' => $validated['admin_email'],
            'name' => $validated['organization_name'],
            'metadata' => [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
            ],
        ]);

        $tenant->update(['stripe_id' => $customer->id]);

        return $customer;
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
        array $sessionOptions = [],
    ): \Stripe\Checkout\Session {
        $priceId = $plan->stripe_price_id ?? $this->createPriceOnTheFly($plan);

        return Cashier::stripe()->checkout->sessions->create(array_merge([
            'customer' => $customerId,
            'client_reference_id' => (string) $tenant->id,
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'subscription_data' => [
                'trial_period_days' => $plan->trial_days,
                'metadata' => [
                    'tenant_id' => $tenant->id,
                ],
            ],
            // Permite códigos de desconto/cupom no checkout
            'allow_promotion_codes' => true,
            // Coleta Tax ID (CNPJ/CPF) e endereço do cliente
            'tax_id_collection' => ['enabled' => true],
            'customer_update' => ['name' => 'auto', 'address' => 'auto'],
            'success_url' => $this->signupSuccessUrl(),
            'cancel_url' => $this->signupCancelUrl($plan->slug),
            'metadata' => [
                'tenant_id' => $tenant->id,
                'plan_slug' => $plan->slug,
            ],
        ], $sessionOptions));
    }

    /**
     * Cria um Produto + Preço no Stripe em tempo de execução quando o plano não possui um stripe_price_id.
     *
     * Usa idempotency keys para evitar criação duplicada em caso de retry.
     */
    public function createPriceOnTheFly(Plan $plan): string
    {
        $this->audit('tenant.signup_price_created_on_the_fly', 'Plano sem stripe_price_id. Criando price emergencialmente.', [
            'plan_id' => $plan->id,
            'plan_slug' => $plan->slug,
            'price_in_cents' => $plan->price,
        ]);

        $idempotencyBase = 'plan-'.$plan->id.'-'.$plan->slug;

        $product = Cashier::stripe()->products->create(
            [
                'name' => $plan->name,
                'description' => $plan->description,
            ],
            ['idempotency_key' => 'product-'.$idempotencyBase]
        );

        $price = Cashier::stripe()->prices->create(
            [
                'product' => $product->id,
                'unit_amount' => $plan->price,
                'currency' => config('cashier.currency', 'brl'),
                'recurring' => ['interval' => 'month'],
            ],
            ['idempotency_key' => 'price-'.$idempotencyBase.'-'.$plan->price]
        );

        $plan->update(['stripe_price_id' => $price->id]);

        return $price->id;
    }

    private function signupSuccessUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/signup/success?session_id={CHECKOUT_SESSION_ID}';
    }

    private function signupCancelUrl(string $planSlug): string
    {
        $query = http_build_query(['plan' => $planSlug, 'cancelled' => 1]);

        return rtrim((string) config('app.frontend_url'), '/').'/cadastro?'.$query.'&session_id={CHECKOUT_SESSION_ID}';
    }
}
