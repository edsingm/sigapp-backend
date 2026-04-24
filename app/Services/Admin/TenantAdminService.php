<?php

namespace App\Services\Admin;

use App\Models\Central\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\Billing\TenantBillingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TenantAdminService
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TenantBillingService $billingService
    ) {}

    public function paginate(?string $search, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->tenantRepository->paginateForAdmin($search, $status, $perPage);
    }

    /**
     * @return array{tenant: Tenant, stats: array<string, int>, finance: array<string, mixed>}
     */
    public function detail(Tenant $tenant): array
    {
        $tenant = $this->tenantRepository->loadWithPlan($tenant);

        return [
            'tenant' => $tenant,
            'stats' => $this->tenantRepository->usageStats($tenant),
            'finance' => $this->billingService->getAdminFinanceOverview($tenant),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activate(Tenant $tenant): array
    {
        return $this->billingService->reconcileTenantActivation($tenant);
    }

    public function suspend(Tenant $tenant): Tenant
    {
        return $this->tenantRepository->suspend($tenant);
    }
}
