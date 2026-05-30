<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleRepository implements RoleRepositoryInterface
{
    public function forSelect(): Collection
    {
        /** @var Collection<int, Role> $roles */
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name']);

        return $roles;
    }

    public function list(?string $search = null): Collection
    {
        $query = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name,guard_name')
            ->withCount('permissions')
            ->orderBy('name');

        if ($search !== null && $search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        /** @var Collection<int, Role> $roles */
        $roles = $query->get();

        return $roles;
    }

    public function findById(int $id): ?Role
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name,guard_name')
            ->withCount('permissions')
            ->find($id);
    }

    public function create(string $name): Role
    {
        /** @var Role $role */
        $role = Role::query()->create([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        return $role;
    }

    public function update(Role $role, string $name): Role
    {
        $role->name = $name;
        $role->save();

        return $role;
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    /**
     * @param  array<int>  $ids
     * @return Collection<int, Permission>
     */
    public function findPermissionsByIds(array $ids): Collection
    {
        /** @var Collection<int, Permission> $permissions */
        $permissions = Permission::query()->whereIn('id', $ids)
            ->where('guard_name', 'web')
            ->get();

        return $permissions;
    }

    public function countUsers(Role $role): int
    {
        $rolePivot = config('permission.table_names.model_has_roles');
        $rolePivotKey = config('permission.column_names.role_foreign_key') ?? 'role_id';

        return (int) DB::table($rolePivot)
            ->where($rolePivotKey, $role->id)
            ->where('model_type', User::class)
            ->count();
    }
}
