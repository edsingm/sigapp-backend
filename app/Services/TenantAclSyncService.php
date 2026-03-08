<?php

namespace App\Services;

use App\Enums\Common\ModulesEnum;
use App\Enums\Common\RolesEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantAclSyncService
{
    private const LEVEL_MAP = [
        'viewer'  => ['viewer'],
        'editor'  => ['viewer', 'editor'],
        'manager' => ['viewer', 'editor', 'manager'],
    ];

    /**
     * Sync system permissions and roles for the current tenant context,
     * applying JSON templates from database/rbacTemplates/.
     *
     * @return array<string, int|string|null>
     */
    public function syncForCurrentTenant(): array
    {
        if (!tenancy()->initialized) {
            throw new \RuntimeException('Tenant ACL sync requer contexto de tenant inicializado.');
        }

        $tenant = tenancy()->tenant;

        if (!$tenant) {
            return ['tenant_id' => null, 'permissions_synced' => 0, 'roles_synced' => 0];
        }

        // 1. Sync permissions
        $allPermissions = $this->generateAllPermissions();

        $synced = 0;
        foreach ($allPermissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if ($permission->wasRecentlyCreated) {
                $synced++;
            }
        }

        // 2. Sync roles and apply templates
        $rolesSynced = 0;

        foreach (RolesEnum::cases() as $roleEnum) {
            Role::firstOrCreate(['name' => $roleEnum->value, 'guard_name' => 'web']);

            $templatePath = database_path('rbacTemplates/' . strtolower($roleEnum->value) . '.json');

            if (!file_exists($templatePath)) {
                continue;
            }

            $template    = json_decode(file_get_contents($templatePath), true);
            $role        = Role::where('name', $roleEnum->value)->where('guard_name', 'web')->first();
            $permissions = $this->resolvePermissions($template['permissions']);

            $role->syncPermissions($permissions);
            $rolesSynced++;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'tenant_id'          => (string) $tenant->id,
            'permissions_synced' => $synced,
            'roles_synced'       => $rolesSynced,
        ];
    }

    /**
     * Generates all permission names from ModulesEnum in dot-notation format.
     *
     * @return array<int, string>
     */
    private function generateAllPermissions(): array
    {
        $levels      = array_keys(self::LEVEL_MAP);
        $permissions = [];

        foreach (ModulesEnum::cases() as $module) {
            if ($module->hasResources()) {
                foreach ($module->resources() as $resource) {
                    foreach ($levels as $level) {
                        $permissions[] = "{$module->value}.{$resource}.{$level}";
                    }
                }
            } else {
                foreach ($levels as $level) {
                    $permissions[] = "{$module->value}.{$level}";
                }
            }
        }

        return $permissions;
    }

    /**
     * Converts a template permissions map into cumulative flat permission names.
     *
     * @param  array<string, string|array<string, string>|null> $modulePermissions
     * @return array<int, string>
     */
    private function resolvePermissions(array $modulePermissions): array
    {
        $permissions = [];

        foreach ($modulePermissions as $moduleKey => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $resource => $level) {
                    if ($level === null) {
                        continue;
                    }
                    foreach (self::LEVEL_MAP[$level] as $permLevel) {
                        $permissions[] = "{$moduleKey}.{$resource}.{$permLevel}";
                    }
                }
            } else {
                foreach (self::LEVEL_MAP[$value] as $permLevel) {
                    $permissions[] = "{$moduleKey}.{$permLevel}";
                }
            }
        }

        return $permissions;
    }
}


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
