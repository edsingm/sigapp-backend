<?php

namespace App\Repositories\Contracts;

use App\Models\Central\Entitlement;
use App\Models\Central\Tenant;
use App\Models\Central\TenantEntitlement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TenantRepositoryInterface
{
    public function findById(string $id): ?Tenant;

    public function findBySlug(string $slug): ?Tenant;

    public function findByIdOrSlug(string $identifier): ?Tenant;

    public function findByStripeId(string $stripeId): ?Tenant;

    public function existsBySlug(string $slug): bool;

    public function existsByDomain(string $domain): bool;

    public function paginateForAdmin(?string $search, ?string $status, int $perPage = 15): LengthAwarePaginator;

    public function loadWithPlan(Tenant $tenant): Tenant;

    public function updatePlan(Tenant $tenant, int $planId): Tenant;

    /**
     * @return array<string, int>
     */
    public function usageStats(Tenant $tenant): array;

    public function suspend(Tenant $tenant): Tenant;

    public function listExtraEntitlements(Tenant $tenant): Collection;

    public function addExtraEntitlement(Tenant $tenant, int $entitlementId, mixed $value, int $price): TenantEntitlement;

    public function updateExtraEntitlement(Tenant $tenant, int $entitlementId, array $data): TenantEntitlement;

    public function removeExtraEntitlement(Tenant $tenant, int $entitlementId): bool;
}
