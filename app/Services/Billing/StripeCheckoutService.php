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
            'payment_method_types' => ['card'],
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
     */
    public function createPriceOnTheFly(Plan $plan): string
    {
        $this->audit('tenant.signup_price_created_on_the_fly', 'Plano sem stripe_price_id. Criando price emergencialmente.', [
            'plan_id' => $plan->id,
            'plan_slug' => $plan->slug,
            'price_in_cents' => $plan->price,
        ]);

        $product = Cashier::stripe()->products->create([
            'name' => $plan->name,
            'description' => $plan->description,
        ]);

        $price = Cashier::stripe()->prices->create([
            'product' => $product->id,
            'unit_amount' => $plan->price,
            'currency' => config('cashier.currency', 'brl'),
            'recurring' => ['interval' => 'month'],
        ]);

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
