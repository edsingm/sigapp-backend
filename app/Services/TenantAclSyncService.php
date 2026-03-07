<?php

namespace App\Services;

use App\Models\Central\PlanRolePermissionTemplate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantAclSyncService
{
    /**
     * Sync system permissions and template-managed roles for the current tenant context.
     *
     * @return array<string, int|string|null>
     */
    public function syncForCurrentTenant(): array
    {
        if (!tenancy()->initialized) {
            throw new \RuntimeException('Tenant ACL sync requer contexto de tenant inicializado.');
        }

        $tenant = tenancy()->tenant;
        $planId = $tenant?->plan_id;

        if (!$tenant || !$planId) {
            return [
                'tenant_id' => $tenant?->id,
                'plan_id' => $planId,
                'permissions_created' => 0,
                'roles_synced' => 0,
                'templates_applied' => 0,
            ];
        }

        $systemPermissions = app(AclPermissionCatalogService::class)->allSystemPermissions();

        $createdPermissions = 0;
        foreach ($systemPermissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);

            if ($permission->wasRecentlyCreated) {
                $createdPermissions++;
            }
        }

        $templateRows = tenancy()->central(function () use ($planId) {
            return PlanRolePermissionTemplate::query()
                ->where('plan_id', $planId)
                ->orderBy('role_slug')
                ->orderBy('permission_name')
                ->get()
                ->groupBy('role_slug');
        });

        $rolesSynced = 0;
        $templatesApplied = 0;

        foreach ($templateRows as $roleSlug => $rows) {
            $role = Role::firstOrCreate([
                'name' => (string) $roleSlug,
                'guard_name' => 'web',
            ]);

            $permissionNames = $rows->pluck('permission_name')->unique()->values()->all();
            $permissions = Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $permissionNames)
                ->get();

            $role->syncPermissions($permissions);

            $rolesSynced++;
            $templatesApplied += count($permissionNames);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'tenant_id' => (string) $tenant->id,
            'plan_id' => (int) $planId,
            'permissions_created' => $createdPermissions,
            'roles_synced' => $rolesSynced,
            'templates_applied' => $templatesApplied,
        ];
    }
}
