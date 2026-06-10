<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditLog;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function countTotalTenants(): int
    {
        return Tenant::query()->count();
    }

    public function countActiveTenants(): int
    {
        return Tenant::query()->where('status', Tenant::STATUS_ACTIVE)->count();
    }

    public function countPendingTenants(): int
    {
        return Tenant::query()->where('status', Tenant::STATUS_PENDING)->count();
    }

    public function countSuspendedTenants(): int
    {
        return Tenant::query()->where('status', Tenant::STATUS_SUSPENDED)->count();
    }

    public function countCancelledTenants(): int
    {
        return Tenant::query()->where('status', Tenant::STATUS_CANCELLED)->count();
    }

    public function countTodayTenants(): int
    {
        return Tenant::query()->whereDate('created_at', today())->count();
    }

    public function countTrialTenants(): int
    {
        return Tenant::query()->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->count();
    }

    public function countTrialExpiredTenants(): int
    {
        return Tenant::query()->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->whereNotIn('status', [Tenant::STATUS_ACTIVE])
            ->count();
    }

    public function calculateMrr(): float
    {
        return Tenant::query()->where('status', Tenant::STATUS_ACTIVE)
            ->whereNotNull('plan_id')
            ->with('plan')
            ->get()
            ->sum(function (Tenant $tenant): float {
                return (float) $tenant->plan()->value('price');
            });
    }

    public function tenantsByPlan(): Collection
    {
        /** @var Collection<int, Plan> $plans */
        $plans = Plan::query()->withCount(['tenants' => function ($query): void {
            $query->where('status', Tenant::STATUS_ACTIVE);
        }])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $plans;
    }

    public function tenantsTrend(int $days = 30): SupportCollection
    {
        $trend = collect();

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $count = Tenant::query()->whereDate('created_at', $date)->count();
            $trend->push([
                'date' => $date->format('d/m'),
                'count' => $count,
            ]);
        }

        return $trend;
    }

    public function recentTenants(int $limit = 5): Collection
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->with('plan')
            ->latest()
            ->take($limit)
            ->get();

        return $tenants;
    }

    public function recentActivity(int $limit = 10): Collection
    {
        /** @var Collection<int, AuditLog> $activities */
        $activities = AuditLog::query()->with('user')
            ->latest()
            ->take($limit)
            ->get();

        return $activities;
    }

    public function recentTenantsSimple(int $limit = 5): Collection
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->latest()
            ->take($limit)
            ->get();

        return $tenants;
    }
}
