<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\DestroyPermissionRequest;
use App\Http\Requests\Tenant\Admin\ListPermissionsRequest;
use App\Http\Requests\Tenant\Admin\ShowPermissionRequest;
use App\Http\Requests\Tenant\StorePermissionRequest;
use App\Http\Requests\Tenant\UpdatePermissionRequest;
use App\Http\Resources\Tenant\Admin\PermissionResource;
use App\Services\Acl\PermissionService;
use App\Services\ApiResponseService;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function index(ListPermissionsRequest $request)
    {
        $permissions = $this->permissionService->list($request->validated('search'));

        return ApiResponseService::success(
            PermissionResource::collection($permissions),
            'Permissões recuperadas com sucesso'
        );
    }

    public function show(ShowPermissionRequest $request, int $id)
    {
        $permission = $this->permissionService->findById($id);

        if (! $permission) {
            return ApiResponseService::notFound('Permissão não encontrada');
        }

        return ApiResponseService::success(
            new PermissionResource($permission),
            'Permissão recuperada com sucesso'
        );
    }

    public function store(StorePermissionRequest $request)
    {
        $validated = $request->validated();
        $permission = $this->permissionService->create($validated['name']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ApiResponseService::created(
            new PermissionResource($permission),
            'Permissão criada com sucesso'
        );
    }

    public function update(UpdatePermissionRequest $request, int $id)
    {
        $permission = $this->permissionService->findById($id);

        if (! $permission) {
            return ApiResponseService::notFound('Permissão não encontrada');
        }

        if ($this->permissionService->isSystemPermission($permission->name)) {
            return ApiResponseService::error(
                'PROTECTED_PERMISSION',
                'Permissões de sistema são protegidas e não podem ser renomeadas.',
                null,
                400
            );
        }

        $validated = $request->validated();
        $permission = $this->permissionService->update($permission, $validated['name']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ApiResponseService::success(
            new PermissionResource($permission),
            'Permissão atualizada com sucesso'
        );
    }

    public function destroy(DestroyPermissionRequest $request, int $id)
    {
        $permission = $this->permissionService->findById($id);

        if (! $permission) {
            return ApiResponseService::notFound('Permissão não encontrada');
        }

        if ($this->permissionService->isSystemPermission($permission->name)) {
            return ApiResponseService::error(
                'PROTECTED_PERMISSION',
                'Permissões de sistema são protegidas e não podem ser removidas.',
                null,
                400
            );
        }

        if (! $this->permissionService->canDelete($permission)) {
            return ApiResponseService::conflict(
                'Não é possível excluir uma permissão já atribuída a roles ou usuários'
            );
        }

        $this->permissionService->delete($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ApiResponseService::noContent();
    }
}
