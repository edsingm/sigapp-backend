<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AdminMfaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminMfaRecoveryCodesRequest;
use App\Http\Requests\Admin\AdminMfaRotateRequest;
use App\Http\Requests\Admin\AdminMfaRotateVerifyRequest;
use App\Http\Requests\Admin\AdminMfaVerifyRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\CentralUserResource;
use App\Models\User;
use App\Services\ApiResponseService;
use App\Services\Auth\AdminAuthService;
use App\Services\Auth\AdminLoginAttemptLogger;
use App\Services\Auth\AdminMfaService;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function __construct(
        private readonly AdminAuthService $adminAuthService,
        private readonly AdminMfaService $adminMfaService,
        private readonly DashboardService $dashboardService,
        private readonly AdminLoginAttemptLogger $loginAttemptLogger,
    ) {}

    /**
     * Login do Administrador
     *
     * POST /api/v1/admin/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $requestId = $request->header('X-Request-ID');
        $email = (string) ($request->validated()['email'] ?? '');

        $result = $this->adminAuthService->attempt(
            $request->validated(),
            $request->ip(),
            $request->userAgent(),
        );

        $this->loginAttemptLogger->record([
            'email' => $email,
            'successful' => $result !== null,
            'stage' => 'password',
            'user_id' => $result !== null ? (int) $result['user']->getKey() : null,
            'failure_reason' => $result === null ? 'invalid_credentials' : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => is_string($requestId) ? $requestId : null,
        ]);

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

        Log::info('Senha de administrador aceita; MFA pendente', [
            'request_id' => $requestId,
            'user_id' => $result['user']->getKey(),
            'status' => 'accepted',
        ]);

        $data = [
            'user' => CentralUserResource::make($result['user'])->resolve(),
            'state' => $result['state'],
            'challenge' => $result['challenge'],
            'expires_at' => $result['expires_at'],
        ];

        if (isset($result['setup'])) {
            $data['setup'] = $result['setup'];
        }

        return $this->noStore(ApiResponseService::success($data, 'MFA_CHALLENGE_CREATED'));
    }

    public function verify(AdminMfaVerifyRequest $request): JsonResponse
    {
        $requestId = $request->header('X-Request-ID');
        $requestId = is_string($requestId) ? $requestId : null;

        try {
            $result = $this->adminMfaService->verifyLogin(
                (string) $request->validated('challenge'),
                $request->validated('code'),
                $request->validated('recovery_code'),
            );
        } catch (AdminMfaException $exception) {
            $this->loginAttemptLogger->record([
                'email' => '',
                'successful' => false,
                'stage' => $request->validated('recovery_code') !== null ? 'recovery' : 'mfa',
                'failure_reason' => $exception->errorCode,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $requestId,
            ]);

            throw $exception;
        }

        /** @var User $user */
        $user = $result['user'];
        $this->loginAttemptLogger->record([
            'email' => $user->email,
            'successful' => true,
            'stage' => $request->validated('recovery_code') !== null ? 'recovery' : 'mfa',
            'user_id' => (int) $user->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $requestId,
        ]);

        $data = [
            'user' => CentralUserResource::make($user)->resolve(),
            'token' => $result['token'],
            'expires_at' => $result['expires_at'],
        ];
        if (isset($result['recovery_codes'])) {
            $data['recovery_codes'] = $result['recovery_codes'];
        }

        return $this->noStore(ApiResponseService::success($data, 'LOGIN_SUCCESS'));
    }

    public function mfaStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponseService::forbidden();
        }

        return ApiResponseService::success($this->adminMfaService->status($user), 'MFA_STATUS_RETRIEVED');
    }

    public function rotate(AdminMfaRotateRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponseService::forbidden();
        }

        $result = $this->adminMfaService->beginRotation(
            $user,
            (string) $request->validated('password'),
            $request->validated('code'),
            $request->validated('recovery_code'),
            $request->ip(),
            $request->userAgent(),
        );

        return $this->noStore(ApiResponseService::success($result, 'MFA_ROTATION_STARTED'));
    }

    public function verifyRotation(AdminMfaRotateVerifyRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponseService::forbidden();
        }

        $result = $this->adminMfaService->verifyRotation(
            $user,
            (string) $request->validated('challenge'),
            (string) $request->validated('code'),
        );

        /** @var User $user */
        $user = $result['user'];

        return $this->noStore(ApiResponseService::success([
            'user' => CentralUserResource::make($user)->resolve(),
            'token' => $result['token'],
            'expires_at' => $result['expires_at'],
            'recovery_codes' => $result['recovery_codes'],
        ], 'MFA_ROTATION_COMPLETED'));
    }

    public function recoveryCodes(AdminMfaRecoveryCodesRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponseService::forbidden();
        }

        $codes = $this->adminMfaService->regenerateRecoveryCodes(
            $user,
            (string) $request->validated('password'),
            $request->validated('code'),
            $request->validated('recovery_code'),
        );

        return $this->noStore(ApiResponseService::success([
            'recovery_codes' => $codes,
        ], 'MFA_RECOVERY_CODES_REGENERATED'));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
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
