<?php

namespace App\Http\Controllers\Api\V1\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    private const PROTECTED_ROLE_NAMES = ['super_admin', 'admin'];

    /**
     * List tenant roles.
     */
    public function index(Request $request)
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('name', 'like', "%{$search}%");
            })
            ->with('permissions:id,name,guard_name')
            ->withCount('permissions')
            ->orderBy('name')
            ->get();

        $rolePivot = config('permission.table_names.model_has_roles');
        $rolePivotKey = config('permission.column_names.role_foreign_key') ?? 'role_id';

        $usersPerRole = DB::table($rolePivot)
            ->select($rolePivotKey, DB::raw('count(*) as total'))
            ->where('model_type', User::class)
            ->groupBy($rolePivotKey)
            ->pluck('total', $rolePivotKey);

        $payload = $roles->map(function (Role $role) use ($usersPerRole) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->map(fn (Permission $permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                ])->values(),
                'permissions_count' => $role->permissions_count,
                'users_count' => (int) ($usersPerRole[$role->id] ?? 0),
                'created_at' => $role->created_at?->toIso8601String(),
                'updated_at' => $role->updated_at?->toIso8601String(),
            ];
        })->values();

        return ApiResponseService::success($payload, 'Roles recuperadas com sucesso');
    }

    /**
     * Show a single tenant role.
     */
    public function show(int $id)
    {
        $role = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name,guard_name')
            ->withCount('permissions')
            ->find($id);

        if (!$role) {
            return ApiResponseService::notFound('Role não encontrada');
        }

        $rolePivot = config('permission.table_names.model_has_roles');
        $rolePivotKey = config('permission.column_names.role_foreign_key') ?? 'role_id';
        $usersCount = DB::table($rolePivot)
            ->where($rolePivotKey, $role->id)
            ->where('model_type', User::class)
            ->count();

        return ApiResponseService::success([
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
            ])->values(),
            'permissions_count' => $role->permissions_count,
            'users_count' => $usersCount,
            'created_at' => $role->created_at?->toIso8601String(),
            'updated_at' => $role->updated_at?->toIso8601String(),
        ], 'Role recuperada com sucesso');
    }

    /**
     * Create a tenant role.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => [
                'integer',
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
            ],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        if (!empty($validated['permission_ids'])) {
            $permissions = Permission::whereIn('id', $validated['permission_ids'])
                ->where('guard_name', 'web')
                ->get();
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ApiResponseService::created([
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions()
                ->select('permissions.id', 'permissions.name')
                ->orderBy('permissions.name')
                ->get(),
            'permissions_count' => $role->permissions()->count(),
            'users_count' => 0,
            'created_at' => $role->created_at?->toIso8601String(),
            'updated_at' => $role->updated_at?->toIso8601String(),
        ], 'Role criada com sucesso');
    }

    /**
     * Update a tenant role.
     */
    public function update(Request $request, int $id)
    {
        $role = Role::query()
            ->where('guard_name', 'web')
            ->find($id);

        if (!$role) {
            return ApiResponseService::notFound('Role não encontrada');
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:120',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($role->id),
            ],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => [
                'integer',
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
            ],
        ]);

        if (array_key_exists('name', $validated)) {
            if (in_array($role->name, self::PROTECTED_ROLE_NAMES, true) && $validated['name'] !== $role->name) {
                return ApiResponseService::error(
                    'PROTECTED_ROLE',
                    'As roles super_admin e admin são protegidas e não podem ser renomeadas.',
                    null,
                    400
                );
            }

            $role->name = $validated['name'];
            $role->save();
        }

        if (array_key_exists('permission_ids', $validated)) {
            if ($role->name === 'super_admin') {
                return ApiResponseService::error(
                    'PROTECTED_ROLE_PERMISSIONS',
                    'As permissões da role super_admin são gerenciadas pelo sistema e não podem ser alteradas manualmente.',
                    null,
                    400
                );
            }

            $permissions = Permission::whereIn('id', $validated['permission_ids'])
                ->where('guard_name', 'web')
                ->get();
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role->load('permissions:id,name,guard_name');
        $role->loadCount('permissions');

        $rolePivot = config('permission.table_names.model_has_roles');
        $rolePivotKey = config('permission.column_names.role_foreign_key') ?? 'role_id';
        $usersCount = DB::table($rolePivot)
            ->where($rolePivotKey, $role->id)
            ->where('model_type', User::class)
            ->count();

        return ApiResponseService::success([
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
            ])->values(),
            'permissions_count' => $role->permissions_count,
            'users_count' => $usersCount,
            'created_at' => $role->created_at?->toIso8601String(),
            'updated_at' => $role->updated_at?->toIso8601String(),
        ], 'Role atualizada com sucesso');
    }

    /**
     * Delete a tenant role.
     */
    public function destroy(int $id)
    {
        $role = Role::query()
            ->where('guard_name', 'web')
            ->find($id);

        if (!$role) {
            return ApiResponseService::notFound('Role não encontrada');
        }

        if (in_array($role->name, self::PROTECTED_ROLE_NAMES, true)) {
            return ApiResponseService::error(
                'PROTECTED_ROLE',
                'As roles super_admin e admin são protegidas e não podem ser removidas.',
                null,
                400
            );
        }

        $rolePivot = config('permission.table_names.model_has_roles');
        $rolePivotKey = config('permission.column_names.role_foreign_key') ?? 'role_id';
        $usersCount = DB::table($rolePivot)
            ->where($rolePivotKey, $role->id)
            ->where('model_type', User::class)
            ->count();

        if ($usersCount > 0) {
            return ApiResponseService::conflict('Não é possível excluir uma role atribuída a usuários');
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ApiResponseService::noContent();
    }
}
