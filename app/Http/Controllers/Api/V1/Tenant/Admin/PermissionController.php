<?php

namespace App\Http\Controllers\Api\V1\Tenant\Admin;

use App\Enums\Common\ModulesEnum;
use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    /**
     * Listar permissões do tenant.
     */
    public function index(Request $request)
    {
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('name', 'like', "%{$search}%");
            })
            ->with('roles:id,name,guard_name')
            ->withCount('roles')
            ->orderBy('name')
            ->get();

        $payload = $permissions->map(function (Permission $permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'roles' => $permission->roles->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ])->values(),
                'roles_count' => $permission->roles_count,
                'created_at' => $permission->created_at?->toIso8601String(),
                'updated_at' => $permission->updated_at?->toIso8601String(),
            ];
        })->values();

        return ApiResponseService::success($payload, 'Permissões recuperadas com sucesso');
    }

    /**
     * Exibir uma única permissão do tenant.
     */
    public function show(int $id)
    {
        $permission = Permission::query()
            ->where('guard_name', 'web')
            ->with('roles:id,name,guard_name')
            ->withCount('roles')
            ->find($id);

        if (! $permission) {
            return ApiResponseService::notFound('Permissão não encontrada');
        }

        return ApiResponseService::success([
            'id' => $permission->id,
            'name' => $permission->name,
            'guard_name' => $permission->guard_name,
            'roles' => $permission->roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])->values(),
            'roles_count' => $permission->roles_count,
            'created_at' => $permission->created_at?->toIso8601String(),
            'updated_at' => $permission->updated_at?->toIso8601String(),
        ], 'Permissão recuperada com sucesso');
    }

    /**
     * Criar uma permissão do tenant.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('permissions', 'name')->where('guard_name', 'web'),
            ],
        ]);

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ApiResponseService::created([
            'id' => $permission->id,
            'name' => $permission->name,
            'guard_name' => $permission->guard_name,
            'roles' => [],
            'roles_count' => 0,
            'created_at' => $permission->created_at?->toIso8601String(),
            'updated_at' => $permission->updated_at?->toIso8601String(),
        ], 'Permissão criada com sucesso');
    }

    /**
     * Atualizar uma permissão do tenant.
     */
    public function update(Request $request, int $id)
    {
        $permission = Permission::query()
            ->where('guard_name', 'web')
            ->with('roles:id,name,guard_name')
            ->withCount('roles')
            ->find($id);

        if (! $permission) {
            return ApiResponseService::notFound('Permissão não encontrada');
        }

        if ($this->isSystemPermission($permission->name)) {
            return ApiResponseService::error(
                'PROTECTED_PERMISSION',
                'Permissões de sistema são protegidas e não podem ser renomeadas.',
                null,
                400
            );
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('permissions', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($permission->id),
            ],
        ]);

        $permission->update(['name' => $validated['name']]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission->load('roles:id,name,guard_name');
        $permission->loadCount('roles');

        return ApiResponseService::success([
            'id' => $permission->id,
            'name' => $permission->name,
            'guard_name' => $permission->guard_name,
            'roles' => $permission->roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])->values(),
            'roles_count' => $permission->roles_count,
            'created_at' => $permission->created_at?->toIso8601String(),
            'updated_at' => $permission->updated_at?->toIso8601String(),
        ], 'Permissão atualizada com sucesso');
    }

    /**
     * Excluir uma permissão do tenant.
     */
    public function destroy(int $id)
    {
        $permission = Permission::query()
            ->where('guard_name', 'web')
            ->find($id);

        if (! $permission) {
            return ApiResponseService::notFound('Permissão não encontrada');
        }

        if ($this->isSystemPermission($permission->name)) {
            return ApiResponseService::error(
                'PROTECTED_PERMISSION',
                'Permissões de sistema são protegidas e não podem ser removidas.',
                null,
                400
            );
        }

        $rolePermissionTable = config('permission.table_names.role_has_permissions');
        $modelPermissionTable = config('permission.table_names.model_has_permissions');
        $permissionPivotKey = config('permission.column_names.permission_foreign_key') ?? 'permission_id';

        $roleUsage = DB::table($rolePermissionTable)
            ->where($permissionPivotKey, $permission->id)
            ->count();

        $userUsage = DB::table($modelPermissionTable)
            ->where($permissionPivotKey, $permission->id)
            ->where('model_type', User::class)
            ->count();

        if ($roleUsage > 0 || $userUsage > 0) {
            return ApiResponseService::conflict(
                'Não é possível excluir uma permissão já atribuída a roles ou usuários'
            );
        }

        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ApiResponseService::noContent();
    }

    /**
     * Verifica se o nome da permissão é uma permissão gerada pelo sistema (formato dot-notation).
     * Permissões de sistema não podem ser renomeadas ou excluídas.
     */
    private function isSystemPermission(string $name): bool
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
