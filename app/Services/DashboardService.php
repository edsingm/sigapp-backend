<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly DashboardRepositoryInterface $repository,
    ) {}

    /**
     * @return array<string, float|int>
     */
    public function stats(): array
    {
        return [
            'total_tenants' => $this->repository->countTotalTenants(),
            'active_tenants' => $this->repository->countActiveTenants(),
            'pending_tenants' => $this->repository->countPendingTenants(),
            'suspended_tenants' => $this->repository->countSuspendedTenants(),
            'cancelled_tenants' => $this->repository->countCancelledTenants(),
            'today_tenants' => $this->repository->countTodayTenants(),
            'trial_tenants' => $this->repository->countTrialTenants(),
            'trial_expired_tenants' => $this->repository->countTrialExpiredTenants(),
            'mrr' => $this->repository->calculateMrr(),
        ];
    }

    /**
     * @return Collection<int, array{name: string, count: int, price: float}>
     */
    public function tenantsByPlan(): Collection
    {
        return $this->repository->tenantsByPlan()
            ->map(fn (\App\Models\Central\Plan $plan) => [
                'name' => $plan->name,
                'count' => (int) $plan->tenants_count,
                'price' => $plan->price,
            ]);
    }

    /**
     * @return Collection<int, array{date: string, count: int}>
     */
    public function tenantsTrend(int $days = 30): Collection
    {
        return $this->repository->tenantsTrend($days);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Central\Tenant>
     */
    public function recentTenants(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->recentTenants($limit);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuditLog>
     */
    public function recentActivity(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->recentActivity($limit);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Central\Tenant>
     */
    public function recentTenantsSimple(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->recentTenantsSimple($limit);
    }
}
