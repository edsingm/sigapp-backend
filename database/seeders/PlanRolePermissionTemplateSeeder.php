<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PlanRolePermissionTemplateSeeder extends Seeder
{
    /**
     * This seeder previously populated PlanRolePermissionTemplate from PlanRoleMatrixTemplateService.
     * The permission system has migrated to dot-notation format (module.resource.level).
     * Role permissions are now seeded directly via RolePermissionSeeder and JSON templates
     * in database/rbacTemplates/, making this seeder a no-op.
     */
    public function run(): void
    {
        $this->command?->info('PlanRolePermissionTemplateSeeder: nenhuma ação necessária (migrado para RolePermissionSeeder).');
    }
}
