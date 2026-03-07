<?php

namespace App\Services;

use App\Models\Central\Plan;

class PlanRoleMatrixTemplateService
{
    /**
     * Build the default role->permissions matrix for a plan.
     *
     * Current behavior mirrors the existing tenant seeders.
     *
     * @return array<string, array<int, string>>
     */
    public function defaultMatrixForPlan(Plan $plan): array
    {
        $all = app(AclPermissionCatalogService::class)->allSystemPermissions();

        $manager = [
            'view any terrenos', 'view terrenos', 'create terrenos', 'update terrenos', 'export terrenos',
            'view any documentos', 'view documentos', 'create documentos', 'update documentos',
            'view any produtos', 'view produtos', 'create produtos', 'update produtos',
            'view any proprietarios', 'view proprietarios', 'create proprietarios', 'update proprietarios',
            'view any regionais', 'view regionais',
            'view any corretores externos', 'view corretores externos',
            'view any viabilidades', 'view viabilidades', 'create viabilidades', 'update viabilidades',
            'duplicate viabilidades', 'compare viabilidades', 'generate dre viabilidades', 'recalculate viabilidades', 'export viabilidades',
            'view any terreno produtos', 'view terreno produtos', 'create terreno produtos', 'update terreno produtos',
            'view any terreno status', 'view terreno status',
        ];

        $user = [
            'view any terrenos', 'view terrenos',
            'view any documentos', 'view documentos',
            'view any produtos', 'view produtos',
            'view any proprietarios', 'view proprietarios',
            'view any regionais', 'view regionais',
            'view any corretores externos', 'view corretores externos',
            'view any viabilidades', 'view viabilidades', 'compare viabilidades',
            'view any terreno produtos', 'view terreno produtos',
            'view any terreno status', 'view terreno status',
        ];

        return [
            'super_admin' => $all,
            'admin' => $all,
            'manager' => $manager,
            'user' => $user,
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
