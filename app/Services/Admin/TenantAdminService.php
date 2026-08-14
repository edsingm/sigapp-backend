<?php

namespace App\Services\Admin;

use App\Models\Central\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\Billing\TenantBillingService;
use App\Traits\LogsAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TenantAdminService
{
    use LogsAudit;

    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TenantBillingService $billingService
    ) {}

    /**
     * @param  array{plan_id?: int|null, on_trial?: bool|null, setup?: string|null}  $filters
     */
    public function paginate(?string $search, ?string $status, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->tenantRepository->paginateForAdmin($search, $status, $perPage, $filters);
    }

    /**
     * @return array{tenant: Tenant, stats: array<string, int|float|null>, finance: array<string, mixed>}
     */
    public function detail(Tenant $tenant): array
    {
        $this->audit('tenant.privileged_access', 'Admin da plataforma acessou o dossiê do tenant.', [
            'tenant_id' => (string) $tenant->getKey(),
            'reason' => 'admin.tenant.show',
        ]);

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
