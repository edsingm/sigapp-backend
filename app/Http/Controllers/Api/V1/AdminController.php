<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\ApiResponseService;
use App\Services\Auth\AdminAuthService;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function __construct(
        private readonly AdminAuthService $adminAuthService,
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * Login do Administrador
     *
     * POST /api/v1/admin/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $requestId = $request->header('X-Request-ID');

        $result = $this->adminAuthService->attempt($request->validated());

        if ($result === null) {
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
            'user_id' => $result['user']->getKey(),
            'status' => 'accepted',
        ]);

        return ApiResponseService::success([
            'user' => $result['user'],
            'token' => $result['token'],
            'expires_at' => $result['expires_at'],
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
            'stats' => $this->dashboardService->basicStats(),
            'recent_tenants' => $this->dashboardService->recentTenantsSimple(5),
        ], language()->t('DASHBOARD_DATA_RETRIEVED'));
    }
}
