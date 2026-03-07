<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * List all users (admins).
     */
    public function index(Request $request)
    {
        $users = User::query()
            ->latest()
            ->paginate(15);

        return ApiResponseService::success($users, 'Lista de usuários recuperada');
    }

    /**
     * Create a new administrative user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'is_admin' => 'boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => $validated['is_admin'] ?? false,
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.created',
            'description' => "Usuário {$user->name} ({$user->id}) criado.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['created_user_id' => $user->id]
        ]);

        return ApiResponseService::success($user, 'Usuário criado com sucesso', 201);
    }

    /**
     * Update a user.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'is_admin' => 'boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.updated',
            'description' => "Usuário {$user->name} ({$user->id}) atualizado.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['updated_user_id' => $user->id]
        ]);

        return ApiResponseService::success($user, 'Usuário atualizado com sucesso');
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return ApiResponseService::error('SELF_DELETION', 'Não é possível excluir a si mesmo');
        }

        $user->delete();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.deleted',
            'description' => "Usuário {$user->name} ({$user->id}) excluído.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['deleted_user_id' => $user->id]
        ]);

        return ApiResponseService::success(null, 'Usuário excluído com sucesso');
    }
}
