<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AclSyncRepository
{
    /**
     * Cria a permissão se ainda não existir; retorna true se foi criada agora.
     */
    public function ensurePermission(string $name, string $guard = 'web'): bool
    {
        return Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard])->wasRecentlyCreated;
    }

    public function ensureRole(string $name, string $guard = 'web'): void
    {
        Role::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
    }

    public function findRole(string $name, string $guard = 'web'): ?Role
    {
        return Role::where('name', $name)->where('guard_name', $guard)->first();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function syncRolePermissions(Role $role, array $permissions): void
    {
        $role->syncPermissions($permissions);
    }

    public function forgetCachedPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
