<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Common\BillingAddonSubscriptionStatus;
use App\Models\Central\BillingAddon;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonSubscription;
use App\Repositories\Contracts\TenantAddonSubscriptionRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class TenantAddonSubscriptionRepository implements TenantAddonSubscriptionRepositoryInterface
{
    /** @return Collection<int, TenantAddonSubscription> */
    public function forTenant(Tenant $tenant, bool $activeOnly = false): Collection
    {
        $query = TenantAddonSubscription::query()
            ->with('addon')
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('id');

        if ($activeOnly) {
            $query->whereIn('status', [
                BillingAddonSubscriptionStatus::ACTIVE->value,
                BillingAddonSubscriptionStatus::TRIALING->value,
                BillingAddonSubscriptionStatus::PAST_DUE->value,
            ]);
        }

        /** @var Collection<int, TenantAddonSubscription> $subscriptions */
        $subscriptions = $query->get();

        return $subscriptions;
    }

    /** @return Collection<int, TenantAddonSubscription> */
    public function forTenantId(string $tenantId): Collection
    {
        /** @var Collection<int, TenantAddonSubscription> $subscriptions */
        $subscriptions = TenantAddonSubscription::query()
            ->with(['addon', 'tenant'])
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get();

        return $subscriptions;
    }

    /** @return Collection<int, TenantAddonSubscription> */
    public function forStripeSubscription(string $stripeSubscriptionId): Collection
    {
        return TenantAddonSubscription::query()
            ->with('addon')
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->get();
    }

    public function findForTenant(Tenant $tenant, int $id): ?TenantAddonSubscription
    {
        return TenantAddonSubscription::query()
            ->with('addon')
            ->where('tenant_id', $tenant->getKey())
            ->whereKey($id)
            ->first();
    }

    public function findByStripeSubscriptionItemId(string $stripeSubscriptionItemId): ?TenantAddonSubscription
    {
        return TenantAddonSubscription::query()
            ->with(['addon', 'tenant'])
            ->where('stripe_subscription_item_id', $stripeSubscriptionItemId)
            ->first();
    }

    public function upsertFromStripe(
        Tenant $tenant,
        BillingAddon $addon,
        string $stripeSubscriptionId,
        string $stripeSubscriptionItemId,
        string $stripePriceId,
        int $quantity,
        BillingAddonSubscriptionStatus $status,
        bool $cancelAtPeriodEnd,
        ?CarbonInterface $currentPeriodStart,
        ?CarbonInterface $currentPeriodEnd,
    ): TenantAddonSubscription {
        return TenantAddonSubscription::query()->updateOrCreate(
            ['stripe_subscription_item_id' => $stripeSubscriptionItemId],
            [
                'tenant_id' => $tenant->getKey(),
                'billing_addon_id' => $addon->getKey(),
                'stripe_subscription_id' => $stripeSubscriptionId,
                'stripe_price_id' => $stripePriceId,
                'quantity' => $quantity,
                'status' => $status,
                'cancel_at_period_end' => $cancelAtPeriodEnd,
                'current_period_start' => $currentPeriodStart,
                'current_period_end' => $currentPeriodEnd,
                'canceled_at' => $status === BillingAddonSubscriptionStatus::CANCELED ? now() : null,
                'last_synced_at' => now(),
            ],
        )->load('addon');
    }

    /** @param list<string> $activeItemIds */
    public function deactivateMissingItems(Tenant $tenant, string $stripeSubscriptionId, array $activeItemIds): int
    {
        return TenantAddonSubscription::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->when($activeItemIds !== [], fn ($query) => $query->whereNotIn('stripe_subscription_item_id', $activeItemIds))
            ->update([
                'status' => BillingAddonSubscriptionStatus::CANCELED,
                'quantity' => 0,
                'canceled_at' => now(),
                'last_synced_at' => now(),
            ]);
    }

    public function markCanceled(Tenant $tenant, string $stripeSubscriptionId): int
    {
        return TenantAddonSubscription::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->update([
                'status' => BillingAddonSubscriptionStatus::CANCELED,
                'quantity' => 0,
                'canceled_at' => now(),
                'last_synced_at' => now(),
            ]);
    }

    public function cancel(TenantAddonSubscription $subscription): TenantAddonSubscription
    {
        $subscription->forceFill([
            'status' => BillingAddonSubscriptionStatus::CANCELED,
            'quantity' => 0,
            'canceled_at' => now(),
            'last_synced_at' => now(),
        ])->save();

        return $subscription->refresh()->load('addon');
    }

    public function deleteForTenant(Tenant $tenant, TenantAddonSubscription $subscription): void
    {
        $subscription->delete();
    }
}
