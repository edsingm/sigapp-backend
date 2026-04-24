<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\CentralUserResource;
use App\Models\User;
use App\Services\ApiResponseService;
use App\Services\Admin\CentralUserService;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use LogsAudit;

    public function __construct(
        private readonly CentralUserService $userService
    ) {}

    /**
     * Lista todos os usuários (admins).
     */
    public function index(Request $request)
    {
        $users = $this->userService
            ->paginate((int) $request->integer('per_page', 15))
            ->through(fn (User $user): array => CentralUserResource::make($user)->resolve());

        return ApiResponseService::paginated($users, 'Lista de usuários recuperada');
    }

    /**
     * Cria um novo usuário administrativo.
     */
    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->create([
            ...$request->validated(),
            'is_admin' => (bool) $request->validated('is_admin', false),
        ]);

        $this->audit('user.created', "Usuário {$user->name} ({$user->id}) criado.", [
            'created_user_id' => $user->id,
        ]);

        return ApiResponseService::created(
            CentralUserResource::make($user)->resolve(),
            'Usuário criado com sucesso'
        );
    }

    /**
     * Exibe um usuário.
     */
    public function show(User $user)
    {
        return ApiResponseService::success(
            CentralUserResource::make($user)->resolve(),
            'Usuário recuperado com sucesso'
        );
    }

    /**
     * Atualiza um usuário.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $user = $this->userService->update($user, $request->validated());

        $this->audit('user.updated', "Usuário {$user->name} ({$user->id}) atualizado.", [
            'updated_user_id' => $user->id,
        ]);

        return ApiResponseService::success(
            CentralUserResource::make($user)->resolve(),
            'Usuário atualizado com sucesso'
        );
    }

    /**
     * Exclui um usuário.
     */
    public function destroy(Request $request, User $user)
    {
        if (! $this->userService->delete($user, $request->user())) {
            return ApiResponseService::error('SELF_DELETION', 'Não é possível excluir a si mesmo');
        }

        $this->audit('user.deleted', "Usuário {$user->name} ({$user->id}) excluído.", [
            'deleted_user_id' => $user->id,
        ]);

        return ApiResponseService::success(null, 'Usuário excluído com sucesso');
    }
}
