<?php

namespace Database\Seeders\Tenant;

use App\Services\AclPermissionCatalogService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get current tenant
        $tenant = tenancy()->tenant;
        
        // Get Plan Slug for conditional seeding from central database context
        $planSlug = tenancy()->central(function () use ($tenant) {
            return $tenant->plan?->slug ?? 'basico';
        });

        $roles = [
            'super_admin' => 'Administrador com acesso total',
            'admin' => 'Administrador',
            'manager' => 'Gerente',
            'user' => 'Usuário padrão',
        ];

        // Legacy permissions still used by older code paths.
        $legacyPermissions = [
            'terrenos.view',
            'terrenos.create',
            'terrenos.edit',
            'terrenos.delete',
        ];

        // Add Manager/Admin permissions for Master and Pro
        if (in_array($planSlug, ['master', 'pro'])) {
            $legacyPermissions = array_merge($legacyPermissions, [
                'users.view',
                'users.create',
                'users.edit',
                'users.delete',
                'viability.view',
                'viability.create',
                'viability.edit',
                'viability.delete',
            ]);
        }

        // Pro specific features
        if ($planSlug === 'pro') {
            $permissionsArr = [
                'reports.view',
                'reports.export',
                'settings.view',
                'settings.edit',
            ];
            $legacyPermissions = array_merge($legacyPermissions, $permissionsArr);
        }

        // Policy-based permissions used by current controllers/policies.
        $policyPermissions = app(AclPermissionCatalogService::class)->allSystemPermissions();

        $permissions = array_values(array_unique(array_merge($legacyPermissions, $policyPermissions)));

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles with permissions
        foreach ($roles as $roleName => $description) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($roleName === 'super_admin' || $roleName === 'admin') {
                $role->syncPermissions($permissions);
                continue;
            }

            if ($roleName === 'manager') {
                $role->syncPermissions([
                    'view any terrenos', 'view terrenos', 'create terrenos', 'update terrenos', 'export terrenos',
                    'view any documentos', 'view documentos', 'create documentos', 'update documentos',
                    'view any produtos', 'view produtos', 'create produtos', 'update produtos',
                    'view any proprietarios', 'view proprietarios', 'create proprietarios', 'update proprietarios',
                    'view any regionais', 'view regionais',
                    'view any corretores externos', 'view corretores externos',
                    'view any viabilidades', 'view viabilidades', 'create viabilidades', 'update viabilidades',
                    'request approval viabilidades', 'approve viabilidades',
                    'duplicate viabilidades', 'compare viabilidades', 'generate dre viabilidades', 'recalculate viabilidades', 'export viabilidades',
                    'view any projetos', 'view projetos', 'create projetos', 'update projetos', 'cancel projetos', 'mark ready projetos',
                    'view any terreno produtos', 'view terreno produtos', 'create terreno produtos', 'update terreno produtos',
                    'view any terreno status', 'view terreno status',
                ]);
                continue;
            }

            if ($roleName === 'user') {
                $role->syncPermissions([
                    'view any terrenos', 'view terrenos',
                    'view any documentos', 'view documentos',
                    'view any produtos', 'view produtos',
                    'view any proprietarios', 'view proprietarios',
                    'view any regionais', 'view regionais',
                    'view any corretores externos', 'view corretores externos',
                    'view any viabilidades', 'view viabilidades', 'compare viabilidades',
                    'view any projetos', 'view projetos',
                    'view any terreno produtos', 'view terreno produtos',
                    'view any terreno status', 'view terreno status',
                ]);
            }
        }
    }
}
