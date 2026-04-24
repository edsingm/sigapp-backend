<?php

namespace App\Http\Controllers\Api\V1\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreUserRequest;
use App\Http\Requests\Tenant\UpdateUserModulePermissionsRequest;
use App\Http\Requests\Tenant\UpdateUserRequest;
use App\Http\Resources\Tenant\UserResource;
use App\Services\Acl\PermissionNameResolver;
use App\Services\ApiResponseService;
use App\Services\Tenant\TenantUserService;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly TenantUserService $userService,
        private readonly PermissionNameResolver $permissions,
    ) {}

    /**
     * Lista usuários do tenant com busca opcional, filtro de função e ordenação.
     */
    public function index(Request $request)
    {
        $users = $this->userService->list(
            search: $request->filled('search') ? $request->string('search')->toString() : null,
            role: $request->filled('role') ? $request->string('role')->toString() : null,
            sort: $request->string('sort', 'name')->toString(),
            order: $request->string('order', 'asc')->toString(),
            perPage: (int) $request->integer('per_page', 15),
        );

        $users->through(fn ($user) => (new UserResource($user))->toArray($request));

        return ApiResponseService::paginated($users, language()->t('USER_LIST_RETRIEVED'));
    }

    /**
     * Exibe um único usuário.
     */
    public function show(int $id)
    {
        $user = $this->userService->findWithRelations($id);

        if (! $user) {
            return ApiResponseService::notFound(language()->t('USER_NOT_FOUND'));
        }

        return ApiResponseService::success(new UserResource($user), language()->t('USER_RETRIEVED'));
    }

    /**
     * Cria um usuário do tenant.
     */
    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->create($request->validated());

        return ApiResponseService::created(new UserResource($user), language()->t('USER_CREATED_SUCCESSFULLY'));
    }

    /**
     * Atualiza um usuário do tenant.
     */
    public function update(UpdateUserRequest $request, int $id)
    {
        $user = $this->userService->findWithRelations($id);

        if (! $user) {
            return ApiResponseService::notFound(language()->t('USER_NOT_FOUND'));
        }

        $error = $this->userService->update($user, $request->validated(), $request->user());

        if ($error === 'LAST_TENANT_ADMIN') {
            return ApiResponseService::error(
                'LAST_TENANT_ADMIN',
                language()->t('USER_ADMIN_CANT_REMOVE_LAST_ADMIN_ROLE'),
                null,
                400
            );
        }

        return ApiResponseService::success(
            new UserResource($user->fresh(['roles', 'department', 'position'])),
            language()->t('USER_UPDATED_SUCCESSFULLY')
        );
    }

    /**
     * Exclui um usuário do tenant.
     */
    public function destroy(Request $request, int $id)
    {
        $user = $this->userService->findWithRelations($id);

        if (! $user) {
            return ApiResponseService::notFound(language()->t('USER_NOT_FOUND'));
        }

        $error = $this->userService->delete($user, $request->user());

        return match ($error) {
            'CANNOT_DELETE_SELF' => ApiResponseService::error('CANNOT_DELETE_SELF', language()->t('USER_CANNOT_DELETE_OWN_ACCOUNT'), null, 400),
            'LAST_TENANT_ADMIN' => ApiResponseService::error('LAST_TENANT_ADMIN', language()->t('USER_ADMIN_CANT_DELETE_LAST_ADMIN'), null, 400),
            default => ApiResponseService::noContent(),
        };
    }

    /**
     * Atualiza as permissões diretas no nível de módulo para um usuário.
     */
    public function updateModulePermissions(UpdateUserModulePermissionsRequest $request, int $id)
    {
        $user = $this->userService->findWithRelations($id);

        if (! $user) {
            return ApiResponseService::notFound(language()->t('USER_NOT_FOUND'));
        }

        $this->userService->updateModulePermissions(
            $user,
            (array) $request->input('permissions', []),
            $this->permissions,
        );

        return ApiResponseService::success(
            new UserResource($user->fresh(['roles', 'department', 'position', 'permissions'])),
            language()->t('USER_PERMISSIONS_UPDATED_SUCCESSFULLY')
        );
    }
}
