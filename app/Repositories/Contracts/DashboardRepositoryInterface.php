<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

interface DashboardRepositoryInterface
{
    public function countTotalTenants(): int;

    public function countActiveTenants(): int;

    public function countPendingTenants(): int;

    public function countSuspendedTenants(): int;

    public function countCancelledTenants(): int;

    public function countTodayTenants(): int;

    public function countTrialTenants(): int;

    public function countTrialExpiredTenants(): int;

    public function calculateMrr(): float;

    /**
     * @return Collection<int, \App\Models\Central\Plan>
     */
    public function tenantsByPlan(): Collection;

    /**
     * @return SupportCollection<int, array{date: string, count: int}>
     */
    public function tenantsTrend(int $days = 30): SupportCollection;

    /**
     * @return Collection<int, \App\Models\Central\Tenant>
     */
    public function recentTenants(int $limit = 5): Collection;

    /**
     * @return Collection<int, \App\Models\AuditLog>
     */
    public function recentActivity(int $limit = 10): Collection;

    public function recentTenantsSimple(int $limit = 5): Collection;
}
