<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\User;
use App\Services\AclPermissionCatalogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class LegalizacaoPermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permissions = collect(app(AclPermissionCatalogService::class)->systemPermissionDefinitions())
            ->filter(fn (array $permission) => in_array($permission['module'] ?? null, ['legalizacoes', 'legalizacao_etapas'], true))
            ->pluck('name')
            ->values()
            ->all();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }

        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($permissions);
        }
    }
}
