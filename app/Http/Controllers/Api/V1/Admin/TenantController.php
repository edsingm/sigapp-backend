<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmTenantWipeRequest;
use App\Http\Requests\Admin\ListTenantsRequest;
use App\Http\Requests\Admin\UpdateTenantStatusRequest;
use App\Http\Resources\AdminTenantDetailResource;
use App\Http\Resources\AdminTenantSummaryResource;
use App\Models\Central\Tenant;
use App\Services\Admin\TenantAdminService;
use App\Services\ApiResponseService;
use App\Services\Privacy\PrivacyRequestService;
use App\Services\Privacy\PrivacySubjectService;
use App\Services\Privacy\TenantLifecycleService;
use App\Traits\LogsAudit;
use Illuminate\Http\JsonResponse;

class TenantController extends Controller
{
    use LogsAudit;

    public function __construct(
        private readonly TenantAdminService $tenantService,
        private readonly PrivacyRequestService $privacyRequests,
        private readonly TenantLifecycleService $lifecycle,
        private readonly PrivacySubjectService $privacySubjects,
    ) {}

    /**
     * Lista todos os tenants com paginação e filtros.
     */
    public function index(ListTenantsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $filters = [
            'plan_id' => isset($validated['plan_id']) ? (int) $validated['plan_id'] : null,
            'on_trial' => array_key_exists('on_trial', $validated) ? $validated['on_trial'] : null,
            'setup' => $validated['setup'] ?? null,
        ];

        $tenants = $this->tenantService
            ->paginate(
                $validated['search'] ?? null,
                $validated['status'] ?? null,
                (int) ($validated['per_page'] ?? 15),
                $filters
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

    public function privilegedAccess(Tenant $tenant): JsonResponse
    {
        $logs = $this->privacyRequests->privilegedAccess((string) $tenant->getKey(), 50);

        return ApiResponseService::paginated($logs, 'PRIVILEGED_ACCESS_RETRIEVED');
    }

    public function offboard(Tenant $tenant): JsonResponse
    {
        $updated = $this->lifecycle->scheduleOffboard($tenant);

        $this->audit('privacy.tenant_offboarded', 'Offboarding do tenant agendado.', [
            'tenant_id' => (string) $updated->getKey(),
            'wipe_scheduled_at' => optional($updated->getAttribute('wipe_scheduled_at'))->toIso8601String(),
        ]);

        return ApiResponseService::success([
            'cancelled_at' => optional($updated->getAttribute('cancelled_at'))->toIso8601String(),
            'wipe_scheduled_at' => optional($updated->getAttribute('wipe_scheduled_at'))->toIso8601String(),
        ], 'PRIVACY_TENANT_OFFBOARDED');
    }

    public function wipe(ConfirmTenantWipeRequest $request, Tenant $tenant): JsonResponse
    {
        $updated = $this->lifecycle->wipe($tenant, force: true);

        $this->audit('privacy.tenant_wiped', 'Wipe imediato do tenant executado.', [
            'tenant_id' => (string) $updated->getKey(),
        ]);

        return ApiResponseService::success([
            'wiped_at' => optional($updated->getAttribute('wiped_at'))->toIso8601String(),
        ], 'PRIVACY_TENANT_WIPED');
    }

    public function portabilityExport(Tenant $tenant): JsonResponse
    {
        if (! (bool) $tenant->getAttribute('database_created')) {
            return ApiResponseService::error('TENANT_NOT_READY', 'TENANT_NOT_FOUND', null, 422);
        }

        $generation = null;
        $tenant->run(function () use (&$generation): void {
            $generation = $this->privacySubjects->queueWorkspaceExportForCurrentTenant();
        });

        if ($generation === null) {
            return ApiResponseService::error('TENANT_NOT_READY', 'TENANT_NOT_FOUND', null, 422);
        }

        $this->audit('privacy.tenant_export_requested', 'Dump do tenant disparado pelo admin da plataforma.', [
            'tenant_id' => (string) $tenant->getKey(),
            'export_id' => $generation->id,
        ]);

        return ApiResponseService::success([
            'export_id' => $generation->id,
            'status' => $generation->status->value,
        ], 'PRIVACY_EXPORT_QUEUED', 202);
    }
}
