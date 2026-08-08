<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Common\TenantAddonPurchaseStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonPurchase;
use App\Repositories\Contracts\TenantAddonPurchaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TenantAddonPurchaseRepository implements TenantAddonPurchaseRepositoryInterface
{
    public function create(array $attributes): TenantAddonPurchase
    {
        return TenantAddonPurchase::query()->create($attributes);
    }

    public function findByCheckoutSessionId(string $sessionId, bool $lockForUpdate = false): ?TenantAddonPurchase
    {
        $query = TenantAddonPurchase::query()
            ->with(['addon', 'tenant'])
            ->where('stripe_checkout_session_id', $sessionId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function update(TenantAddonPurchase $purchase, array $attributes): TenantAddonPurchase
    {
        $purchase->update($attributes);

        return $purchase->refresh();
    }

    public function paidForTenant(Tenant $tenant): Collection
    {
        /** @var Collection<int, TenantAddonPurchase> $purchases */
        $purchases = TenantAddonPurchase::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', TenantAddonPurchaseStatus::PAID->value)
            ->orderBy('id')
            ->get();

        return $purchases;
    }

    public function paidQuantitiesForTenant(Tenant $tenant): array
    {
        return TenantAddonPurchase::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', TenantAddonPurchaseStatus::PAID->value)
            ->selectRaw('billing_addon_id, SUM(quantity) AS aggregate_quantity')
            ->groupBy('billing_addon_id')
            ->pluck('aggregate_quantity', 'billing_addon_id')
            ->map(static fn (mixed $quantity): int => (int) $quantity)
            ->all();
    }
}
