<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Common\ModulesEnum;
use App\Models\Tenant\User;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function list(?string $search = null): Collection
    {
        $query = Permission::query()
            ->where('guard_name', 'web')
            ->with('roles:id,name,guard_name')
            ->withCount('roles')
            ->orderBy('name');

        if ($search !== null && $search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->get();
    }

    public function findById(int $id): ?Permission
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->with('roles:id,name,guard_name')
            ->withCount('roles')
            ->find($id);
    }

    public function create(string $name): Permission
    {
        return Permission::create([
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }

    public function update(Permission $permission, string $name): Permission
    {
        $permission->update(['name' => $name]);

        return $permission->refresh();
    }

    public function delete(Permission $permission): void
    {
        $permission->delete();
    }

    public function countRoleUsage(Permission $permission): int
    {
        $rolePermissionTable = config('permission.table_names.role_has_permissions');
        $permissionPivotKey = config('permission.column_names.permission_foreign_key') ?? 'permission_id';

        return (int) DB::table($rolePermissionTable)
            ->where($permissionPivotKey, $permission->id)
            ->count();
    }

    public function countUserUsage(Permission $permission): int
    {
        $modelPermissionTable = config('permission.table_names.model_has_permissions');
        $permissionPivotKey = config('permission.column_names.permission_foreign_key') ?? 'permission_id';

        return (int) DB::table($modelPermissionTable)
            ->where($permissionPivotKey, $permission->id)
            ->where('model_type', User::class)
            ->count();
    }

    public function isSystemPermission(string $name): bool
    {
        $parts = explode('.', $name);
        $levels = ['viewer', 'editor', 'manager'];

        if (count($parts) === 3) {
            [$module, $resource, $level] = $parts;
            $mod = ModulesEnum::tryFrom($module);

            return $mod !== null && in_array($resource, $mod->submodules(), true) && in_array($level, $levels, true);
        }

        if (count($parts) === 2) {
            [$module, $level] = $parts;
            $mod = ModulesEnum::tryFrom($module);

            return $mod !== null && ! $mod->hasSubmodules() && in_array($level, $levels, true);
        }

        return false;
    }
}
