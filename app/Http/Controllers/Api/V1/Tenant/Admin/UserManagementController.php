<?php

namespace App\Http\Controllers\Api\V1\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\DestroyUserRequest;
use App\Http\Requests\Tenant\StoreUserRequest;
use App\Http\Requests\Tenant\UpdateUserModulePermissionsRequest;
use App\Http\Requests\Tenant\UpdateUserRequest;
use App\Http\Resources\Tenant\UserResource;
use App\Services\Acl\PermissionNameResolver;
use App\Services\ApiResponseService;
use App\Services\Tenant\TenantUserService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly TenantUserService $userService,
        private readonly PermissionNameResolver $permissions,
    ) {}

    /**
     * Lista usuários do tenant com busca, filtros e ordenação.
     *
     * Query params: search, role, status, department_id, without_department,
     * incomplete, sort, order, per_page.
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->filled('status') ? $request->string('status')->toString() : null;
        $allowedStatuses = ['Active', 'Inactive', 'Suspended'];
        if ($status !== null && ! in_array($status, $allowedStatuses, true)) {
            $status = null;
        }

        $users = $this->userService->list(
            search: $request->filled('search') ? $request->string('search')->toString() : null,
            role: $request->filled('role') ? $request->string('role')->toString() : null,
            sort: $request->string('sort', 'name')->toString(),
            order: $request->string('order', 'asc')->toString(),
            perPage: (int) $request->integer('per_page', 15),
            status: $status,
            departmentId: $request->filled('department_id')
                ? (int) $request->integer('department_id')
                : null,
            withoutDepartment: $request->boolean('without_department'),
            incomplete: $request->boolean('incomplete'),
        );

        $users->through(fn ($user) => (new UserResource($user))->toArray($request));

        return ApiResponseService::paginated($users, language()->t('USER_LIST_RETRIEVED'));
    }

    /**
     * Exibe um único usuário.
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->findWithRelations($id);

        if (! $user) {
            return ApiResponseService::notFound(language()->t('USER_NOT_FOUND'));
        }

        return ApiResponseService::success(new UserResource($user), language()->t('USER_RETRIEVED'));
    }

    /**
     * Cria um usuário do tenant.
     * Aceita `invite=true` para criar sem senha e enviar e-mail de definição.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['invite'] = $request->boolean('invite');

        try {
            $user = $this->userService->create($data);
        } catch (UniqueConstraintViolationException $exception) {
            return $this->uniqueConstraintResponse($exception);
        }

        return ApiResponseService::created(new UserResource($user), language()->t('USER_CREATED_SUCCESSFULLY'));
    }

    /**
     * Reenvia o convite (link de definição de senha) para o usuário.
     */
    public function sendInvite(int $id): JsonResponse
    {
        $user = $this->userService->findWithRelations($id);

        if (! $user) {
            return ApiResponseService::notFound(language()->t('USER_NOT_FOUND'));
        }

        $status = $this->userService->sendInviteLink($user);

        if ($status !== Password::RESET_LINK_SENT) {
            return ApiResponseService::error(
                'INVITE_SEND_FAILED',
                'Não foi possível enviar o convite por e-mail.',
                ['status' => $status],
                422
            );
        }

        return ApiResponseService::success(
            ['status' => $status],
            'Convite enviado com sucesso.'
        );
    }

    /**
     * Atualiza um usuário do tenant.
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->userService->findWithRelations($id);

        if (! $user) {
            return ApiResponseService::notFound(language()->t('USER_NOT_FOUND'));
        }

        try {
            $error = $this->userService->update($user, $request->validated(), $request->user());
        } catch (UniqueConstraintViolationException $exception) {
            return $this->uniqueConstraintResponse($exception);
        }

        if ($error === 'LAST_TENANT_ADMIN') {
            return ApiResponseService::error(
                'LAST_TENANT_ADMIN',
                language()->t('USER_ADMIN_CANT_REMOVE_LAST_ADMIN_ROLE'),
                null,
                400
            );
        }

        return ApiResponseService::success(
            new UserResource($user->fresh(['roles', 'department'])),
            language()->t('USER_UPDATED_SUCCESSFULLY')
        );
    }

    /**
     * Converte unique violations de banco em 422 legível (pt-BR).
     */
    private function uniqueConstraintResponse(UniqueConstraintViolationException $exception): JsonResponse
    {
        $sql = strtolower($exception->getMessage());
        $field = 'geral';
        $message = 'Já existe um registro com estes dados.';

        if (str_contains($sql, 'users_email_unique') || str_contains($sql, '(email)')) {
            $field = 'email';
            $message = 'Já existe um usuário com este e-mail neste tenant.';
        } elseif (str_contains($sql, 'users_cpf_unique') || str_contains($sql, '(cpf)')) {
            $field = 'cpf';
            $message = 'Já existe um usuário com este CPF neste tenant.';
        }

        return ApiResponseService::validationError([
            $field => [$message],
        ]);
    }

    /**
     * Exclui um usuário do tenant.
     */
    public function destroy(DestroyUserRequest $request, int $id): JsonResponse
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
    public function updateModulePermissions(UpdateUserModulePermissionsRequest $request, int $id): JsonResponse
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
            new UserResource($user->fresh(['roles', 'department', 'permissions'])),
            language()->t('USER_PERMISSIONS_UPDATED_SUCCESSFULLY')
        );
    }
}
