<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditLog;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function countTotalTenants(): int
    {
        return Tenant::count();
    }

    public function countActiveTenants(): int
    {
        return Tenant::where('status', Tenant::STATUS_ACTIVE)->count();
    }

    public function countPendingTenants(): int
    {
        return Tenant::where('status', Tenant::STATUS_PENDING)->count();
    }

    public function countSuspendedTenants(): int
    {
        return Tenant::where('status', Tenant::STATUS_SUSPENDED)->count();
    }

    public function countCancelledTenants(): int
    {
        return Tenant::where('status', Tenant::STATUS_CANCELLED)->count();
    }

    public function countTodayTenants(): int
    {
        return Tenant::whereDate('created_at', today())->count();
    }

    public function countTrialTenants(): int
    {
        return Tenant::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->count();
    }

    public function countTrialExpiredTenants(): int
    {
        return Tenant::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->whereNotIn('status', [Tenant::STATUS_ACTIVE])
            ->count();
    }

    public function calculateMrr(): float
    {
        return Tenant::where('status', Tenant::STATUS_ACTIVE)
            ->whereNotNull('plan_id')
            ->with('plan')
            ->get()
            ->sum(function (Tenant $tenant): float {
                return $tenant->plan?->price ?? 0.0;
            });
    }

    public function tenantsByPlan(): Collection
    {
        return Plan::withCount(['tenants' => function ($query) {
            $query->where('status', Tenant::STATUS_ACTIVE);
        }])
            ->where('is_active', true)
            ->ordered()
            ->get();
    }

    public function tenantsTrend(int $days = 30): SupportCollection
    {
        $trend = collect();

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $count = Tenant::whereDate('created_at', $date)->count();
            $trend->push([
                'date' => $date->format('d/m'),
                'count' => $count,
            ]);
        }

        return $trend;
    }

    public function recentTenants(int $limit = 5): Collection
    {
        return Tenant::with('plan')
            ->latest()
            ->take($limit)
            ->get();
    }

    public function recentActivity(int $limit = 10): Collection
    {
        return AuditLog::with('user')
            ->latest()
            ->take($limit)
            ->get();
    }

    public function recentTenantsSimple(int $limit = 5): Collection
    {
        return Tenant::latest()
            ->take($limit)
            ->get();
    }
}
