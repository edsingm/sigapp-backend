<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListTenantsRequest;
use App\Http\Requests\Admin\UpdateTenantStatusRequest;
use App\Http\Resources\AdminTenantDetailResource;
use App\Http\Resources\AdminTenantSummaryResource;
use App\Models\Central\Tenant;
use App\Services\ApiResponseService;
use App\Services\Admin\TenantAdminService;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantAdminService $tenantService
    ) {}

    /**
     * Lista todos os tenants com paginação e filtros.
     */
    public function index(ListTenantsRequest $request): JsonResponse
    {
        $tenants = $this->tenantService
            ->paginate(
                $request->validated('search'),
                $request->validated('status'),
                (int) $request->validated('per_page', 15)
            )
            ->through(fn (Tenant $tenant): array => AdminTenantSummaryResource::make($tenant)->resolve());

        return ApiResponseService::paginated($tenants, 'Lista de tenants recuperada');
    }

    /**
     * Obtém detalhes de um tenant específico.
     */
    public function show(Tenant $tenant): JsonResponse
    {
        return ApiResponseService::success(
            AdminTenantDetailResource::make($this->tenantService->detail($tenant))->resolve(),
            'Detalhes do tenant recuperados'
        );
    }

    /**
     * Ativa um tenant.
     */
    public function activate(UpdateTenantStatusRequest $request, Tenant $tenant): JsonResponse
    {
        if ($tenant->isActive()) {
            return ApiResponseService::error('ALREADY_ACTIVE', 'Tenant já está ativo');
        }

        try {
            $reconciliation = $this->tenantService->activate($tenant);
        } catch (\Exception $e) {
            return ApiResponseService::error(
                'BILLING_RECONCILIATION_ERROR',
                'UNKNOWN_ERROR',
                app()->environment('local') ? $e->getMessage() : null,
                500
            );
        }

        if (! ($reconciliation['eligible'] ?? false)) {
            return ApiResponseService::conflict('BILLING_STATE_INVALID');
        }

        // Registrar ação
        $this->audit('tenant.activated', "Tenant {$tenant->getAttribute('name')} ({$tenant->id}) ativado após reconciliação de billing.", [
            'tenant_id' => $tenant->id,
            'source' => $reconciliation['source'] ?? null,
            'stripe_status' => $reconciliation['stripe_status'] ?? null,
        ]);

        return ApiResponseService::success(
            AdminTenantSummaryResource::make($tenant->fresh('plan'))->resolve(),
            'Tenant ativado com sucesso'
        );
    }

    /**
     * Suspende um tenant.
     */
    public function suspend(UpdateTenantStatusRequest $request, Tenant $tenant): JsonResponse
    {
        if ((string) $tenant->getAttribute('status') === Tenant::STATUS_SUSPENDED) {
            return ApiResponseService::error('ALREADY_SUSPENDED', 'Tenant já está suspenso');
        }

        $tenant = $this->tenantService->suspend($tenant);

        // Registrar ação
        $this->audit('tenant.suspended', "Tenant {$tenant->getAttribute('name')} ({$tenant->id}) suspenso manualmente.", [
            'tenant_id' => $tenant->id,
        ]);

        return ApiResponseService::success(
            AdminTenantSummaryResource::make($tenant->loadMissing('plan'))->resolve(),
            'Tenant suspenso com sucesso'
        );
    }
}
