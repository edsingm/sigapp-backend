<?php

namespace App\Repositories;

use App\Models\Central\Entitlement;
use App\Models\Central\Tenant;
use App\Models\Central\TenantEntitlement;
use App\Models\Tenant\Produto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Stancl\Tenancy\Database\Models\Domain;

class TenantRepository implements TenantRepositoryInterface
{
    public function paginateForAdmin(?string $search, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        $query = Tenant::query()->with('plan');

        if ($search !== null && $search !== '') {
            $query->where(function ($tenantQuery) use ($search): void {
                $tenantQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('admin_email', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->latest()->paginate($perPage);
    }

    public function loadWithPlan(Tenant $tenant): Tenant
    {
        return $tenant->load('plan');
    }

    /**
     * @return array<string, int>
     */
    public function usageStats(Tenant $tenant): array
    {
        $stats = [
            'users_count' => 0,
            'terrenos_count' => 0,
            'products_count' => 0,
            'storage_used' => 0,
        ];

        if (! (bool) $tenant->getAttribute('database_created') || $tenant->getAttribute('setup_completed_at') === null) {
            return $stats;
        }

        try {
            tenancy()->initialize($tenant);

            $stats['users_count'] = User::count();
            $stats['terrenos_count'] = Terreno::count();
            $stats['products_count'] = Produto::count();
        } catch (\Throwable) {
            // Keep zeroed stats when the tenant database is unavailable.
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $stats;
    }

    public function suspend(Tenant $tenant): Tenant
    {
        $tenant->suspend();

        return $tenant->refresh();
    }

    public function findById(string $id): ?Tenant
    {
        return Tenant::query()->find($id);
    }

    public function findBySlug(string $slug): ?Tenant
    {
        return Tenant::query()->where('slug', $slug)->first();
    }

    public function findByIdOrSlug(string $identifier): ?Tenant
    {
        return Tenant::query()->where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->first();
    }

    public function findByStripeId(string $stripeId): ?Tenant
    {
        return Tenant::query()->where('stripe_id', $stripeId)->first();
    }

    public function existsBySlug(string $slug): bool
    {
        return Tenant::query()->where('slug', $slug)->exists();
    }

    public function existsByDomain(string $domain): bool
    {
        return Domain::query()->where('domain', $domain)->exists();
    }

    public function updatePlan(Tenant $tenant, int $planId): Tenant
    {
        $tenant->update(['plan_id' => $planId]);

        // Invalida cache do tenant para que os limites do novo plano sejam aplicados imediatamente
        cache()->forget('tenant:'.(string) $tenant->getAttribute('slug'));

        return $tenant->refresh();
    }

    public function listExtraEntitlements(Tenant $tenant): Collection
    {
        return $tenant->extraEntitlements()->with('entitlement')->get();
    }

    public function addExtraEntitlement(Tenant $tenant, int $entitlementId, mixed $value, int $price): TenantEntitlement
    {
        return TenantEntitlement::create([
            'tenant_id' => $tenant->id,
            'entitlement_id' => $entitlementId,
            'value' => $value,
            'price' => $price,
        ]);
    }

    public function updateExtraEntitlement(Tenant $tenant, int $entitlementId, array $data): TenantEntitlement
    {
        $record = TenantEntitlement::query()->where('tenant_id', $tenant->id)
            ->where('entitlement_id', $entitlementId)
            ->firstOrFail();

        $record->update($data);

        return $record->refresh();
    }

    public function removeExtraEntitlement(Tenant $tenant, int $entitlementId): bool
    {
        return TenantEntitlement::query()->where('tenant_id', $tenant->id)
            ->where('entitlement_id', $entitlementId)
            ->firstOrFail()
            ->delete() === true;
    }
}
