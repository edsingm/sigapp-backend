<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant\Admin;

use App\Enums\Common\RolesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\DestroyRoleRequest;
use App\Http\Requests\Tenant\Admin\ListRolesRequest;
use App\Http\Requests\Tenant\Admin\ShowRoleRequest;
use App\Http\Requests\Tenant\StoreRoleRequest;
use App\Http\Requests\Tenant\UpdateRoleRequest;
use App\Http\Resources\Tenant\Admin\RoleResource;
use App\Http\Resources\Tenant\Admin\RoleSelectResource;
use App\Services\Acl\RoleService;
use App\Services\ApiResponseService;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    public function forSelect()
    {
        $roles = $this->roleService->forSelect();

        return ApiResponseService::success(
            RoleSelectResource::collection($roles),
            'Roles recuperadas com sucesso'
        );
    }

    public function index(ListRolesRequest $request)
    {
        $roles = $this->roleService->list($request->validated('search'));
        $usersPerRole = collect($roles)->mapWithKeys(fn ($role) => [
            $role->id => $this->roleService->countUsers($role),
        ]);

        $payload = $roles->map(function ($role) use ($usersPerRole) {
            $resource = RoleResource::withUsersCount($role, (int) ($usersPerRole[$role->id] ?? 0));

            return $resource->toArray(request());
        })->values();

        return ApiResponseService::success($payload, 'Roles recuperadas com sucesso');
    }

    public function show(ShowRoleRequest $request, int $id)
    {
        $role = $this->roleService->findById($id);

        if (! $role) {
            return ApiResponseService::notFound('Role não encontrada');
        }

        $usersCount = $this->roleService->countUsers($role);

        $resource = RoleResource::withUsersCount($role, $usersCount);

        return ApiResponseService::success($resource, 'Role recuperada com sucesso');
    }

    public function store(StoreRoleRequest $request)
    {
        $validated = $request->validated();
        $role = $this->roleService->create(
            $validated['name'],
            $validated['permission_ids'] ?? []
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ApiResponseService::created(
            new RoleResource($role),
            'Role criada com sucesso'
        );
    }

    public function update(UpdateRoleRequest $request, int $id)
    {
        $role = $this->roleService->findById($id);

        if (! $role) {
            return ApiResponseService::notFound('Role não encontrada');
        }

        $validated = $request->validated();

        if (array_key_exists('name', $validated)) {
            if ($this->roleService->isProtected($role) && $validated['name'] !== $role->name) {
                return ApiResponseService::error(
                    'PROTECTED_ROLE',
                    'As roles do sistema são protegidas e não podem ser renomeadas.',
                    null,
                    400
                );
            }
        }

        if (
            array_key_exists('permission_ids', $validated)
            && $role->name === RolesEnum::ADMIN->value
        ) {
            return ApiResponseService::error(
                'PROTECTED_ROLE_PERMISSIONS',
                'As permissões da role ADMIN são gerenciadas pelo sistema e não podem ser alteradas manualmente.',
                null,
                400
            );
        }

        $role = $this->roleService->update(
            $role,
            $validated['name'] ?? $role->name,
            array_key_exists('permission_ids', $validated) ? $validated['permission_ids'] : null
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ApiResponseService::success(
            RoleResource::withUsersCount($role, $this->roleService->countUsers($role)),
            'Role atualizada com sucesso'
        );
    }

    public function destroy(DestroyRoleRequest $request, int $id)
    {
        $role = $this->roleService->findById($id);

        if (! $role) {
            return ApiResponseService::notFound('Role não encontrada');
        }

        if ($this->roleService->isProtected($role)) {
            return ApiResponseService::error(
                'PROTECTED_ROLE',
                'As roles do sistema são protegidas e não podem ser removidas.',
                null,
                400
            );
        }

        if (! $this->roleService->canDelete($role)) {
            return ApiResponseService::conflict('Não é possível excluir uma role atribuída a usuários');
        }

        $this->roleService->delete($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ApiResponseService::noContent();
    }
}
