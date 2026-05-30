<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Repositories\Contracts\CentralUserRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function __construct(
        private readonly CentralUserRepositoryInterface $centralUserRepository,
        private readonly DashboardRepositoryInterface $dashboardRepository,
    ) {}

    /**
     * Login do Administrador
     *
     * POST /api/v1/admin/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $requestId = $request->header('X-Request-ID');

        $user = $this->centralUserRepository->findByEmail($credentials['email']);

        if (
            ! $user
            || ! Hash::check($credentials['password'], (string) $user->getAttribute('password'))
            || ! (bool) $user->getAttribute('is_admin')
        ) {
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
            'user_id' => $user->getKey(),
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

        $accessToken = $tokenResult->accessToken;
        $expiresAt = $accessToken->getAttribute('expires_at');

        return ApiResponseService::success([
            'user' => $user,
            'token' => $tokenResult->plainTextToken,
            'expires_at' => $expiresAt instanceof \DateTimeInterface ? $expiresAt->format(\DateTimeInterface::ATOM) : null,
        ], language()->t('LOGIN_SUCCESS'));
    }

    /**
     * Estatísticas do Dashboard do Administrador
     *
     * GET /api/v1/admin/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponseService::success([
            'stats' => [
                'total_tenants' => $this->dashboardRepository->countTotalTenants(),
                'active_tenants' => $this->dashboardRepository->countActiveTenants(),
            ],
            'recent_tenants' => $this->dashboardRepository->recentTenantsSimple(5),
        ], language()->t('DASHBOARD_DATA_RETRIEVED'));
    }
}
