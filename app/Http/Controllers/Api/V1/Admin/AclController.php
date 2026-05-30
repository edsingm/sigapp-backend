<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Common\ModulesEnum;
use App\Http\Controllers\Controller;
use App\Models\Central\PlanRolePermissionTemplate;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\PlanRolePermissionTemplateRepositoryInterface;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AclController extends Controller
{
    public function __construct(
        private readonly PlanRepositoryInterface $planRepository,
        private readonly PlanRolePermissionTemplateRepositoryInterface $templateRepository,
    ) {}
    /**
     * Obtém o catálogo de permissões do sistema agrupado por módulo.
     */
    public function catalog()
    {
        $levels = ['viewer', 'editor', 'manager'];
        $grouped = [];

        foreach (ModulesEnum::cases() as $module) {
            $permissions = [];

            if ($module->hasSubmodules()) {
                foreach ($module->submodules() as $resource) {
                    foreach ($levels as $level) {
                        $permissions[] = [
                            'name' => "{$module->value}.{$resource}.{$level}",
                            'module' => $module->value,
                            'resource' => $resource,
                            'level' => $level,
                        ];
                    }
                }
            } else {
                foreach ($levels as $level) {
                    $permissions[] = [
                        'name' => "{$module->value}.{$level}",
                        'module' => $module->value,
                        'level' => $level,
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
     * Obtém o modelo de matriz de cargo/permissão para um plano.
     */
    public function planRoleMatrix(Request $request, int $planId)
    {
        $plan = $this->planRepository->findById($planId);

        if (! $plan) {
            return ApiResponseService::notFound('Plano não encontrado');
        }

        $templates = $this->templateRepository->findByPlanId($plan->id);

        /** @var Collection<string, Collection<int, PlanRolePermissionTemplate>> $templatesByRole */
        $templatesByRole = $templates->groupBy('role_slug');

        $grouped = [];

        foreach ($templatesByRole as $roleSlug => $rows) {
            $permissions = [];

            foreach ($rows as $row) {
                $permissions[] = [
                    'name' => $row->permission_name,
                    'is_required' => (bool) $row->is_required,
                    'is_default' => (bool) $row->is_default,
                ];
            }

            $grouped[] = [
                'role_slug' => $roleSlug,
                'permissions' => $permissions,
                'permissions_count' => count($permissions),
            ];
        }

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
