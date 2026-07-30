<?php

namespace App\Repositories;

use App\Models\Central\Tenant;
use App\Models\Central\TenantEntitlement;
use App\Models\Tenant\AiRequestLog;
use App\Models\Tenant\Produto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\PlanMatrixService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Stancl\Tenancy\Database\Models\Domain;

class TenantRepository implements TenantRepositoryInterface
{
    /**
     * @param  array{plan_id?: int|null, on_trial?: bool|null, setup?: string|null}  $filters
     */
    public function paginateForAdmin(?string $search, ?string $status, int $perPage = 15, array $filters = []): LengthAwarePaginator
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

        $planId = $filters['plan_id'] ?? null;
        if (is_int($planId) && $planId > 0) {
            $query->where('plan_id', $planId);
        }

        if (array_key_exists('on_trial', $filters) && is_bool($filters['on_trial'])) {
            if ($filters['on_trial']) {
                $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', now());
            } else {
                $query->where(function ($q): void {
                    $q->whereNull('trial_ends_at')
                        ->orWhere('trial_ends_at', '<=', now());
                });
            }
        }

        $setup = $filters['setup'] ?? null;
        if ($setup === 'complete') {
            $query->whereNotNull('setup_completed_at');
        } elseif ($setup === 'incomplete') {
            $query->whereNull('setup_completed_at');
        }

        return $query->latest()->paginate($perPage);
    }

    public function loadWithPlan(Tenant $tenant): Tenant
    {
        return $tenant->load('plan');
    }

    /** @return Collection<int, Tenant> */
    public function expiredPending(int $limit): Collection
    {
        $query = Tenant::query();
        $query->where('status', Tenant::STATUS_PENDING);
        $query->where('created_at', '<', now()->subDay());
        $query->with('plan');
        $query->orderBy('id');
        $query->limit($limit);

        return $query->get();
    }

    /**
     * @return array<string, int|float|null>
     */
    public function usageStats(Tenant $tenant): array
    {
        $stats = [
            'users_count' => 0,
            'terrenos_count' => 0,
            'products_count' => 0,
            'storage_used' => 0,
            // Consumo de IA do mês (USD) vs orçamento do plano
            'ai_budget_usd' => null,
            'ai_spent_usd' => 0.0,
            'ai_usage_percent' => null,
            'ai_requests' => 0,
            'ai_tokens' => 0,
        ];

        try {
            if (is_int($tenant->getAttribute('plan_id'))) {
                $limits = app(PlanMatrixService::class)->resolveForTenant($tenant)['limits'];
                if (array_key_exists('ai_budget', $limits)) {
                    $stats['ai_budget_usd'] = (float) $limits['ai_budget'];
                }
            }
        } catch (\Throwable) {
            // plano sem matriz / sem plan_id
        }

        if (! (bool) $tenant->getAttribute('database_created') || $tenant->getAttribute('setup_completed_at') === null) {
            return $this->withAiUsagePercent($stats);
        }

        try {
            tenancy()->initialize($tenant);

            $stats['users_count'] = User::count();
            $stats['terrenos_count'] = Terreno::count();
            $stats['products_count'] = Produto::count();

            $monthStart = Carbon::now()->startOfMonth();
            $aiAgg = AiRequestLog::query()
                ->where('created_at', '>=', $monthStart)
                ->selectRaw(
                    'COUNT(*) as requests_count, COALESCE(SUM(total_tokens), 0) as tokens_sum, COALESCE(SUM(estimated_cost_usd), 0) as cost_sum'
                )
                ->first();

            if ($aiAgg !== null) {
                $stats['ai_requests'] = (int) ($aiAgg->requests_count ?? 0);
                $stats['ai_tokens'] = (int) ($aiAgg->tokens_sum ?? 0);
                $stats['ai_spent_usd'] = round((float) ($aiAgg->cost_sum ?? 0), 4);
            }
        } catch (\Throwable) {
            // Keep zeroed stats when the tenant database is unavailable.
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $this->withAiUsagePercent($stats);
    }

    /**
     * @param  array<string, int|float|null>  $stats
     * @return array<string, int|float|null>
     */
    private function withAiUsagePercent(array $stats): array
    {
        $budget = $stats['ai_budget_usd'] ?? null;
        $spent = (float) ($stats['ai_spent_usd'] ?? 0);

        if (is_numeric($budget) && (float) $budget > 0) {
            $stats['ai_usage_percent'] = round(($spent / (float) $budget) * 100, 1);
        } elseif (is_numeric($budget) && (float) $budget === 0.0) {
            $stats['ai_usage_percent'] = $spent > 0 ? 100.0 : 0.0;
        } else {
            $stats['ai_usage_percent'] = null;
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

    public function readyForEntitlementAudit(?string $identifier = null): iterable
    {
        foreach (Tenant::all() as $model) {
            if (! $model instanceof Tenant) {
                continue;
            }

            $matchesIdentifier = $identifier === null
                || $identifier === ''
                || $model->id === $identifier
                || $model->slug === $identifier;

            if ($matchesIdentifier && $model->database_created) {
                yield $model;
            }
        }
    }
}
