<?php

namespace App\Services\Billing;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Notifications\PaymentFailedNotification;
use Carbon\Carbon;
use Laravel\Cashier\Cashier;
use Stripe\StripeClient;

class TenantBillingService
{
    public const STATUS_NOOP = 'noop';

    public const STATUS_ACTIVE = Tenant::STATUS_ACTIVE;

    public const STATUS_SUSPENDED = Tenant::STATUS_SUSPENDED;

    public const STATUS_CANCELLED = Tenant::STATUS_CANCELLED;

    protected function stripe(): StripeClient
    {
        return Cashier::stripe();
    }

    public function getSignupContractAcceptance(Tenant $tenant): array
    {
        $virtualAcceptance = $tenant->getAttribute('signup_contract_acceptance');
        if (is_array($virtualAcceptance)) {
            return $virtualAcceptance;
        }

        $rawData = $tenant->getAttribute('data');
        if (is_array($rawData)) {
            return (array) ($rawData['signup_contract_acceptance'] ?? []);
        }

        if (is_string($rawData) && $rawData !== '') {
            $decoded = json_decode($rawData, true);
            if (is_array($decoded)) {
                return (array) ($decoded['signup_contract_acceptance'] ?? []);
            }
        }

        return [];
    }

    public function getSignupCheckoutSessionId(Tenant $tenant): ?string
    {
        $sessionId = data_get($this->getSignupContractAcceptance($tenant), 'stripe_checkout_session_id');

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    public function storeSignupCheckoutSessionId(Tenant $tenant, string $sessionId): void
    {
        if ($sessionId === '') {
            return;
        }

        $tenantData = $tenant->getAttribute('data');
        if (! is_array($tenantData)) {
            $tenantData = [];
        }

        data_set($tenantData, 'signup_contract_acceptance.stripe_checkout_session_id', $sessionId);

        $tenant->update(['data' => $tenantData]);
        $tenant->setAttribute('signup_contract_acceptance', data_get($tenantData, 'signup_contract_acceptance', []));
    }

    public function matchesSignupCheckoutSession(Tenant $tenant, ?string $sessionId): bool
    {
        if (! is_string($sessionId) || $sessionId === '') {
            return false;
        }

        return $this->getSignupCheckoutSessionId($tenant) === $sessionId;
    }

    public function findTenantBySignupCheckoutSessionId(string $sessionId): ?Tenant
    {
        return Tenant::query()
            ->where('data->signup_contract_acceptance->stripe_checkout_session_id', $sessionId)
            ->first();
    }

    public function retrieveCheckoutSession(string $sessionId): object
    {
        return $this->stripe()->checkout->sessions->retrieve($sessionId, []);
    }

    public function expireCheckoutSession(string $sessionId): void
    {
        $this->stripe()->checkout->sessions->expire($sessionId, []);
    }

    public function deleteCustomer(string $customerId): void
    {
        $this->stripe()->customers->delete($customerId, []);
    }

    public function retrieveSubscription(string $subscriptionId): object
    {
        return $this->stripe()->subscriptions->retrieve($subscriptionId, []);
    }

    public function cancelSubscription(string $subscriptionId): object
    {
        return $this->stripe()->subscriptions->cancel($subscriptionId, []);
    }

    public function createBillingPortalUrl(Tenant $tenant, ?string $returnUrl = null): string
    {
        return $tenant->billingPortalUrl($returnUrl);
    }

    public function syncPlanFromPriceId(Tenant $tenant, ?string $priceId): void
    {
        if (! $priceId) {
            return;
        }

        $newPlan = Plan::where('stripe_price_id', $priceId)->first();

        if ($newPlan && $newPlan->id !== $tenant->plan_id) {
            $tenant->update(['plan_id' => $newPlan->id]);
        }
    }

    /**
     * Aplica o status da assinatura do Stripe ao tenant.
     *
     * - active/trialing  → ativa o tenant
     * - past_due         → notifica o usuário (Stripe ainda está em retry, não suspende)
     * - unpaid/incomplete_expired → suspende
     * - canceled         → cancela
     * - outros           → noop
     */
    public function applyStripeSubscriptionStatus(Tenant $tenant, ?string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active', 'trialing' => tap(self::STATUS_ACTIVE, fn () => $tenant->activate()),
            'past_due' => tap(self::STATUS_NOOP, fn () => $tenant->notify(
                new PaymentFailedNotification($tenant->name, 0, null)
            )),
            'unpaid', 'incomplete_expired' => tap(self::STATUS_SUSPENDED, fn () => $tenant->suspend()),
            'canceled' => tap(self::STATUS_CANCELLED, fn () => $tenant->cancel()),
            default => self::STATUS_NOOP,
        };
    }

    public function syncSubscription(Tenant $tenant, string $subscriptionId): void
    {
        $stripeSubscription = $this->retrieveSubscription($subscriptionId);

        $subscription = $tenant->subscriptions()->firstOrNew([
            'stripe_id' => $stripeSubscription->id,
        ]);

        $trialEndsAt = $stripeSubscription->trial_end
            ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
            : null;

        $endsAt = $stripeSubscription->cancel_at
            ? Carbon::createFromTimestamp($stripeSubscription->cancel_at)
            : null;

        $subscription->fill([
            'type' => 'default',
            'stripe_status' => $stripeSubscription->status,
            'stripe_price' => $stripeSubscription->items->data[0]->price->id ?? null,
            'quantity' => $stripeSubscription->items->data[0]->quantity ?? 1,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => $endsAt,
        ]);
        $subscription->save();

        foreach ($stripeSubscription->items->data as $item) {
            $subscription->items()->updateOrCreate([
                'stripe_id' => $item->id,
            ], [
                'stripe_product' => $item->price->product,
                'stripe_price' => $item->price->id,
                'quantity' => $item->quantity ?? 1,
            ]);
        }

        // Sincroniza trial_ends_at do Stripe de volta para a coluna do tenant,
        // corrigindo possível dessincronização entre o valor local (calculado no signup)
        // e o valor real registrado no Stripe (contado a partir do checkout completion).
        if ($stripeSubscription->trial_end) {
            if (! $tenant->trial_ends_at || ! $tenant->trial_ends_at->eq($trialEndsAt)) {
                $tenant->update(['trial_ends_at' => $trialEndsAt]);
            }
        } elseif ($tenant->trial_ends_at && $stripeSubscription->status !== 'trialing') {
            // Trial encerrado no Stripe mas ainda definido localmente — limpa
            $tenant->update(['trial_ends_at' => null]);
        }
    }

    public function reconcileTenantActivation(Tenant $tenant): array
    {
        if ($tenant->onTrial() && ! $tenant->stripe_subscription_id) {
            $tenant->activate();

            return [
                'eligible' => true,
                'source' => 'local_trial',
                'stripe_status' => null,
            ];
        }

        if (! $tenant->stripe_subscription_id) {
            return [
                'eligible' => false,
                'source' => 'missing_subscription_reference',
                'stripe_status' => null,
            ];
        }

        $subscription = $this->retrieveSubscription($tenant->stripe_subscription_id);
        $stripeStatus = (string) ($subscription->status ?? '');

        $tenant->update([
            'stripe_id' => $subscription->customer ?? $tenant->stripe_id,
            'stripe_subscription_id' => $subscription->id ?? $tenant->stripe_subscription_id,
        ]);

        $this->syncPlanFromPriceId($tenant, $subscription->items->data[0]->price->id ?? null);
        $this->syncSubscription($tenant, $subscription->id);

        $this->applyStripeSubscriptionStatus($tenant, $stripeStatus);

        return [
            'eligible' => in_array($stripeStatus, ['active', 'trialing'], true),
            'source' => 'stripe',
            'stripe_status' => $stripeStatus,
        ];
    }
}
