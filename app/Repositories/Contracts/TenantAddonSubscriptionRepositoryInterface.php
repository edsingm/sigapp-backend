<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\Common\BillingAddonSubscriptionStatus;
use App\Models\Central\BillingAddon;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonSubscription;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

interface TenantAddonSubscriptionRepositoryInterface
{
    /** @return Collection<int, TenantAddonSubscription> */
    public function forTenant(Tenant $tenant, bool $activeOnly = false): Collection;

    /** @return Collection<int, TenantAddonSubscription> */
    public function forTenantId(string $tenantId): Collection;

    /** @return Collection<int, TenantAddonSubscription> */
    public function forStripeSubscription(string $stripeSubscriptionId): Collection;

    public function findForTenant(Tenant $tenant, int $id): ?TenantAddonSubscription;

    public function findByStripeSubscriptionItemId(string $stripeSubscriptionItemId): ?TenantAddonSubscription;

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
    ): TenantAddonSubscription;

    /** @param list<string> $activeItemIds */
    public function deactivateMissingItems(Tenant $tenant, string $stripeSubscriptionId, array $activeItemIds): int;

    public function markCanceled(Tenant $tenant, string $stripeSubscriptionId): int;

    public function cancel(TenantAddonSubscription $subscription): TenantAddonSubscription;

    public function deleteForTenant(Tenant $tenant, TenantAddonSubscription $subscription): void;
}
