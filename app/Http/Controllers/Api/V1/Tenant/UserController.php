<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantUserRequest;
use App\Http\Requests\Tenant\UpdateTenantUserRequest;
use App\Http\Resources\UserResource;
use App\Repositories\Tenant\UserRepository;
use App\Services\ApiResponseService;
use App\Services\Tenant\TenantUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    public function __construct(
        private readonly TenantUserService $userService,
        private readonly UserRepository $userRepository,
    ) {}

    /**
     * Lista todos os usuários com busca opcional, filtro de função e ordenação.
     *
     * GET /api/v1/users
     */
    public function index(Request $request)
    {
        $tenantId = tenant('id') ?? 'central';
        $filters = $request->only(['search', 'role', 'sort', 'order', 'per_page', 'page']);
        $cacheKey = "tenant:{$tenantId}:users:index:".md5(json_encode($filters));

        $users = Cache::tags(["tenant:{$tenantId}:users"])->remember($cacheKey, now()->addMinutes(30), function () use ($request) {
            return $this->userService->list(
                search: $request->get('search'),
                role: $request->get('role'),
                sort: $request->get('sort', 'name'),
                order: $request->get('order', 'asc'),
                perPage: (int) $request->get('per_page', 15),
            );
        });

        return ApiResponseService::paginated($users);
    }

    /**
     * Busca um usuário específico.
     *
     * GET /api/v1/users/{id}
     */
    public function show(int $id)
    {
        $user = $this->userRepository->find($id);

        if (! $user) {
            return ApiResponseService::notFound('Usuário não encontrado');
        }

        return ApiResponseService::success(new UserResource($user), 'Usuário recuperado com sucesso');
    }

    /**
     * Cria um novo usuário.
     *
     * POST /api/v1/users
     */
    public function store(StoreTenantUserRequest $request)
    {
        $user = $this->userService->create($request->validated());

        return ApiResponseService::created(new UserResource($user), 'Usuário criado com sucesso');
    }

    /**
     * Atualiza um usuário.
     *
     * PUT /api/v1/users/{id}
     */
    public function update(UpdateTenantUserRequest $request, int $id)
    {
        $user = $this->userRepository->find($id);

        if (! $user) {
            return ApiResponseService::notFound('Usuário não encontrado');
        }

        $error = $this->userService->update($user, $request->validated(), $request->user());

        if ($error === 'LAST_TENANT_ADMIN') {
            return ApiResponseService::error('LAST_TENANT_ADMIN', 'Não é possível remover a função de administrador do último administrador', null, 400);
        }

        return ApiResponseService::success(new UserResource($user->fresh()), 'Usuário atualizado com sucesso');
    }

    /**
     * Exclui um usuário.
     *
     * DELETE /api/v1/users/{id}
     */
    public function destroy(Request $request, int $id)
    {
        $user = $this->userRepository->find($id);

        if (! $user) {
            return ApiResponseService::notFound('Usuário não encontrado');
        }

        $error = $this->userService->delete($user, $request->user());

        return match ($error) {
            'CANNOT_DELETE_SELF' => ApiResponseService::error('CANNOT_DELETE_SELF', 'Você não pode excluir sua própria conta', null, 400),
            'LAST_TENANT_ADMIN' => ApiResponseService::error('LAST_TENANT_ADMIN', 'Não é possível excluir o último administrador do tenant', null, 400),
            default => ApiResponseService::noContent(),
        };
    }

    /**
     * Busca usuários para dropdown de seleção.
     *
     * Retorna apenas id e name — dados não sensíveis acessíveis a qualquer
     * usuário autenticado do tenant para preencher campos de "responsável".
     *
     * GET /api/v1/users/for-select
     */
    public function usersForSelect()
    {
        $users = $this->userRepository->listForSelect();

        return ApiResponseService::success($users, 'Usuários carregados com sucesso');
    }
}
