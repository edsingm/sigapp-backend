<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\AuditLog;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
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
     * @return Collection<int, Plan>
     */
    public function tenantsByPlan(): Collection;

    /**
     * @return SupportCollection<int, array{date: string, count: int}>
     */
    public function tenantsTrend(int $days = 30): SupportCollection;

    /**
     * @return Collection<int, Tenant>
     */
    public function recentTenants(int $limit = 5): Collection;

    /**
     * @return Collection<int, AuditLog>
     */
    public function recentActivity(int $limit = 10): Collection;

    public function recentTenantsSimple(int $limit = 5): Collection;
}
