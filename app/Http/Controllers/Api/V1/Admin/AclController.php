<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Common\ModulesEnum;
use App\Http\Controllers\Controller;
use App\Models\Central\Plan;
use App\Models\Central\PlanRolePermissionTemplate;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class AclController extends Controller
{
    /**
     * Get system permission catalog grouped by module.
     */
    public function catalog()
    {
        $levels  = ['viewer', 'editor', 'manager'];
        $grouped = [];

        foreach (ModulesEnum::cases() as $module) {
            $permissions = [];

            if ($module->hasResources()) {
                foreach ($module->resources() as $resource) {
                    foreach ($levels as $level) {
                        $permissions[] = [
                            'name'     => "{$module->value}.{$resource}.{$level}",
                            'module'   => $module->value,
                            'resource' => $resource,
                            'level'    => $level,
                        ];
                    }
                }
            } else {
                foreach ($levels as $level) {
                    $permissions[] = [
                        'name'   => "{$module->value}.{$level}",
                        'module' => $module->value,
                        'level'  => $level,
                    ];
                }
            }

            $grouped[$module->value] = $permissions;
        }

        return ApiResponseService::success([
            'system_permissions' => $grouped,
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
