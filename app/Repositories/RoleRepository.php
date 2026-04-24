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
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name']);
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

        return $query->get();
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
        return Role::create([
            'name' => $name,
            'guard_name' => 'web',
        ]);
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
        return Permission::whereIn('id', $ids)
            ->where('guard_name', 'web')
            ->get();
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
