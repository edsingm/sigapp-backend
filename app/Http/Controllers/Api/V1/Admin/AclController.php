<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Plan;
use App\Models\Central\PlanRolePermissionTemplate;
use App\Services\AclPermissionCatalogService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class AclController extends Controller
{
    public function __construct(
        protected AclPermissionCatalogService $permissionCatalog
    ) {
    }

    /**
     * Get system permission catalog grouped by module.
     */
    public function catalog()
    {
        return ApiResponseService::success([
            'system_permissions' => $this->permissionCatalog->groupedForUi(),
            'deprecated_permissions' => $this->permissionCatalog->deprecatedPermissions(),
        ], 'Catálogo de permissões recuperado com sucesso');
    }

    /**
     * Get the role/permission matrix template for a plan.
     */
    public function planRoleMatrix(Request $request, int $planId)
    {
        $plan = Plan::query()->find($planId);

        if (!$plan) {
            return ApiResponseService::notFound('Plano não encontrado');
        }

        $templates = PlanRolePermissionTemplate::query()
            ->where('plan_id', $plan->id)
            ->orderBy('role_slug')
            ->orderBy('permission_name')
            ->get();

        $grouped = $templates
            ->groupBy('role_slug')
            ->map(function ($rows, string $roleSlug) {
                return [
                    'role_slug' => $roleSlug,
                    'permissions' => $rows->map(fn (PlanRolePermissionTemplate $row) => [
                        'name' => $row->permission_name,
                        'is_required' => (bool) $row->is_required,
                        'is_default' => (bool) $row->is_default,
                    ])->values(),
                    'permissions_count' => $rows->count(),
                ];
            })
            ->values();

        return ApiResponseService::success([
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
            ],
            'roles' => $grouped,
        ], 'Matriz de permissões do plano recuperada com sucesso');
    }
}
