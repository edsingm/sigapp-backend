<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * Login do Administrador
     *
     * POST /api/v1/admin/login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string'],
        ]);

        $requestId = $request->header('X-Request-ID');

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->is_admin) {
            Log::warning('Login de administrador rejeitado', [
                'request_id' => $requestId,
                'status' => 'rejected',
            ]);

            return ApiResponseService::error(
                'UNAUTHORIZED',
                language()->t('INVALID_CREDENTIALS'),
                null,
                401
            );
        }

        Log::info('Login de administrador aceito', [
            'request_id' => $requestId,
            'user_id' => $user->id,
            'status' => 'accepted',
        ]);

        if ($request->has('device_name')) {
            $user->tokens()->where('name', $credentials['device_name'])->delete();
        }

        $tokenResult = $user->createToken(
            $credentials['device_name'] ?? 'admin-token',
            ['admin'], // Adiciona habilidade específica para admin
            now()->addHours(12)
        );

        return ApiResponseService::success([
            'user' => $user,
            'token' => $tokenResult->plainTextToken,
            'expires_at' => $tokenResult->accessToken->expires_at?->toIso8601String(),
        ], language()->t('LOGIN_SUCCESS'));
    }

    /**
     * Estatísticas do Dashboard do Administrador
     *
     * GET /api/v1/admin/dashboard
     */
    public function dashboard()
    {
        // Por enquanto, retorna algumas estatísticas simples
        // Podemos expandir isso mais tarde para buscar contagens reais das tabelas Tenant/Central
        // Assumindo que temos acesso aos modelos Central aqui, já que isso roda no contexto central

        $totalTenants = Tenant::count();
        $recentTenants = Tenant::latest()->take(5)->get();

        return ApiResponseService::success([
            'stats' => [
                'total_tenants' => $totalTenants,
                'active_tenants' => $totalTenants, // Lógica de espaço reservado
            ],
            'recent_tenants' => $recentTenants,
        ], language()->t('DASHBOARD_DATA_RETRIEVED'));
    }
}
