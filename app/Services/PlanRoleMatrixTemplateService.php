<?php

namespace App\Services;

use App\Enums\AccessLevel;
use App\Enums\Common\ModulesEnum;
use App\Models\Central\Plan;

class PlanRoleMatrixTemplateService
{
    /**
     * Default module/sub-module → access level templates per role.
     *
     *   super_admin / admin → MANAGER everywhere
     *   manager             → MANAGER on viabilidades, EDITOR on operational, VIEWER on config
     *   user                → VIEWER everywhere
     *
     * For modules with sub-modules the map value is an array keyed by sub-module.
     *
     * @return array<string, array<int, string>>
     */
    public function defaultMatrixForPlan(Plan $plan): array
    {
        $service = app(ModuleAccessService::class);

        // Build all-manager and all-viewer maps respecting sub-modules
        $allManager = [];
        $allViewer  = [];

        foreach (ModulesEnum::cases() as $module) {
            if ($module->hasSubModules()) {
                $allManager[$module->value] = array_fill_keys($module->subModules(), AccessLevel::MANAGER);
                $allViewer[$module->value]  = array_fill_keys($module->subModules(), AccessLevel::VIEWER);
            } else {
                $allManager[$module->value] = AccessLevel::MANAGER;
                $allViewer[$module->value]  = AccessLevel::VIEWER;
            }
        }

        $managerTemplate = [
            ModulesEnum::TERRENOS->value => [
                'predio'    => AccessLevel::EDITOR,
                'casa'      => AccessLevel::EDITOR,
                'comercial' => AccessLevel::EDITOR,
            ],
            ModulesEnum::DOCUMENTOS->value          => AccessLevel::EDITOR,
            ModulesEnum::PRODUTOS->value            => AccessLevel::EDITOR,
            ModulesEnum::PROPRIETARIOS->value       => AccessLevel::EDITOR,
            ModulesEnum::REGIONAIS->value           => AccessLevel::VIEWER,
            ModulesEnum::CORRETORES_EXTERNOS->value => AccessLevel::VIEWER,
            ModulesEnum::VIABILIDADES->value        => AccessLevel::MANAGER,
            ModulesEnum::PROJETOS->value            => AccessLevel::VIEWER,
            ModulesEnum::TERRENO_PRODUTOS->value    => AccessLevel::EDITOR,
            ModulesEnum::TERRENO_STATUS->value      => AccessLevel::VIEWER,
            ModulesEnum::LEGALIZACOES->value        => AccessLevel::VIEWER,
            ModulesEnum::LEGALIZACAO_ETAPAS->value  => AccessLevel::VIEWER,
        ];

        return [
            'super_admin' => $service->flatPermissionsFromMap($allManager),
            'admin'       => $service->flatPermissionsFromMap($allManager),
            'manager'     => $service->flatPermissionsFromMap($managerTemplate),
            'user'        => $service->flatPermissionsFromMap($allViewer),
        ];
    }

    /**
     * @return array<int, array{plan_id:int,role_slug:string,permission_name:string,is_required:bool,is_default:bool}>
     */
    public function rowsForPlan(Plan $plan): array
    {
        $rows = [];
        $matrix = $this->defaultMatrixForPlan($plan);

        foreach ($matrix as $roleSlug => $permissions) {
            foreach (array_values(array_unique($permissions)) as $permissionName) {
                $rows[] = [
                    'plan_id' => (int) $plan->id,
                    'role_slug' => $roleSlug,
                    'permission_name' => $permissionName,
                    'is_required' => in_array($roleSlug, ['super_admin', 'admin'], true),
                    'is_default' => true,
                ];
            }
        }

        return $rows;
    }
}
