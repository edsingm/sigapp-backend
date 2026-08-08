<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonPurchase;
use Illuminate\Database\Eloquent\Collection;

interface TenantAddonPurchaseRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TenantAddonPurchase;

    public function findByCheckoutSessionId(string $sessionId, bool $lockForUpdate = false): ?TenantAddonPurchase;

    /** @param array<string, mixed> $attributes */
    public function update(TenantAddonPurchase $purchase, array $attributes): TenantAddonPurchase;

    /** @return Collection<int, TenantAddonPurchase> */
    public function paidForTenant(Tenant $tenant): Collection;

    /** @return array<int, int> */
    public function paidQuantitiesForTenant(Tenant $tenant): array;
}
