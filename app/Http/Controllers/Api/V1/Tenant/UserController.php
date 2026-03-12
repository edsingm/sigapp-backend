<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Tenant\User;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * List all users.
     *
     * GET /api/v1/users
     */
    public function index(Request $request)
    {
        $tenantId = tenant('id') ?? 'central';
        $filters = $request->only(['search', 'role', 'sort', 'order', 'per_page', 'page']);
        $cacheKey = "tenant:{$tenantId}:users:index:" . md5(json_encode($filters));

        $users = Cache::tags(["tenant:{$tenantId}:users"])->remember($cacheKey, now()->addMinutes(30), function () use ($request) {
            $query = User::query();

            // Search
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filter by role
            if ($request->has('role')) {
                $query->role($request->get('role'));
            }

            // Sort
            if ($request->has('sort')) {
                $order = $request->get('order', 'asc');
                $query->orderBy($request->get('sort'), $order);
            } else {
                $query->orderBy('name');
            }

            $perPage = min($request->get('per_page', 15), 100);

            return $query->paginate($perPage);
        });

        return ApiResponseService::paginated($users);
    }

    /**
     * Get a specific user.
     *
     * GET /api/v1/users/{id}
     */
    public function show(int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return ApiResponseService::notFound('Usuário não encontrado');
        }

        return ApiResponseService::success(
            new UserResource($user),
            'Usuário recuperado com sucesso'
        );
    }

    /**
     * Create a new user.
     *
     * POST /api/v1/users
     */
    public function store(Request $request)
    {
        $tenant = tenant();
        $limitService = new \App\Services\LimitEnforcementService($tenant);

        if (!$limitService->canCreateUser()) {
            return ApiResponseService::error(
                'LIMIT_EXCEEDED',
                'Limite de usuários atingido para o seu plano.',
                null,
                403
            );
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['sometimes', 'string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        if (isset($validated['role'])) {
            $user->assignRole($validated['role']);
        } else {
            $user->assignRole('user');
        }

        return ApiResponseService::created(
            new UserResource($user),
            'Usuário criado com sucesso'
        );
    }

    /**
     * Update a user.
     *
     * PUT /api/v1/users/{id}
     */
    public function update(Request $request, int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return ApiResponseService::notFound('Usuário não encontrado');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $id],
            'password' => ['sometimes', Password::defaults()],
            'role' => ['sometimes', 'string', 'exists:roles,name'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return ApiResponseService::success(
            new UserResource($user->fresh()),
            'Usuário atualizado com sucesso'
        );
    }

    /**
     * Delete a user.
     *
     * DELETE /api/v1/users/{id}
     */
    public function destroy(Request $request, int $id)
    {
        $user = User::find($id);

        if (!$user) {
            return ApiResponseService::notFound('Usuário não encontrado');
        }

        // Prevent deleting self
        if ($request->user()->id === $user->id) {
            return ApiResponseService::error(
                'CANNOT_DELETE_SELF',
                'Você não pode excluir sua própria conta',
                null,
                400
            );
        }

        // Prevent deleting the only super admin (legacy compatibility)
        if ($user->hasAnyRole(['SUPER_ADMIN', 'super_admin'])) {
            $superAdminCount = User::role('SUPER_ADMIN')->count() + User::role('super_admin')->count();

            if ($superAdminCount <= 1) {
                return ApiResponseService::error(
                    'LAST_SUPER_ADMIN',
                    'Não é possível excluir o último super administrador',
                    null,
                    400
                );
            }
        }

        $user->delete();

        return ApiResponseService::noContent();
    }

    /**
     * Get users for select dropdown.
     *
     * GET /api/v1/users/for-select
     */
    public function usersForSelect()
    {
        Gate::authorize('viewAny', \App\Models\Tenant\Terreno::class);

        $users = User::select('id', 'name')
            ->orderBy('name')
            ->get();

        return ApiResponseService::success($users, 'Usuários carregados com sucesso');
    }
}
