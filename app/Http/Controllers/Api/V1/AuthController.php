<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\CentralUserResource;
use App\Http\Resources\UserResource;
use App\Models\Central\Tenant;
use App\Models\User as CentralUser;
use App\Services\ApiResponseService;
use App\Services\Auth\CentralLoginBrokerService;
use App\Services\Auth\TenantPasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function login(LoginRequest $request, CentralLoginBrokerService $broker)
    {
        if (!tenancy()->initialized) {
            $tenantIdentifier = $this->resolveLocalTenantIdentifier($request);

            if ($tenantIdentifier !== null) {
                $tenant = Tenant::query()
                    ->where('id', $tenantIdentifier)
                    ->orWhere('slug', $tenantIdentifier)
                    ->first();

                if (!$tenant) {
                    return ApiResponseService::notFound('TENANT_NOT_FOUND');
                }

                tenancy()->initialize($tenant);

                try {
                    return $this->attemptTenantLogin($request->validated(), $request);
                } finally {
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                }
            }

            $result = $broker->attemptCentralLogin(
                (string) $request->validated('email'),
                (string) $request->validated('password'),
                $request->validated('device_name'),
                $request
            );

            if (($result['next_action'] ?? null) === 'unauthorized') {
                return ApiResponseService::error(
                    'UNAUTHORIZED',
                    'INVALID_CREDENTIALS',
                    null,
                    401
                );
            }

            return ApiResponseService::success(
                $result,
                ($result['next_action'] ?? null) === 'choose_tenant'
                    ? 'CHOOSE_TENANT'
                    : 'REDIRECT_READY'
            );
        }

        return $this->attemptTenantLogin($request->validated(), $request);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function attemptTenantLogin(array $credentials, Request $request)
    {
        $user = \App\Models\Tenant\User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return ApiResponseService::error(
                'UNAUTHORIZED',
                'INVALID_CREDENTIALS',
                null,
                401
            );
        }

        if ($request->has('device_name')) {
            $user->tokens()->where('name', $credentials['device_name'])->delete();
        }

        $tokenResult = $user->createToken(
            $credentials['device_name'] ?? 'tenant-api-token',
            ['tenant-api'],
            now()->addDays(7)
        );

        return ApiResponseService::success([
            'user' => new UserResource($user),
            'token' => $tokenResult->plainTextToken,
            'abilities' => ['tenant-api'],
            'expires_at' => $tokenResult->accessToken->expires_at?->toIso8601String(),
        ], 'LOGIN_SUCCESS');
    }

    private function resolveLocalTenantIdentifier(Request $request): ?string
    {
        if (!app()->environment(['local', 'testing'])) {
            return null;
        }

        $fromBody = $request->input('tenant_identifier');
        if (is_string($fromBody) && trim($fromBody) !== '') {
            return trim($fromBody);
        }

        $fromHeader = $request->header('X-Tenant');
        if (is_string($fromHeader) && trim($fromHeader) !== '') {
            return trim($fromHeader);
        }

        return null;
    }

    public function selectTenant(Request $request, CentralLoginBrokerService $broker)
    {
        $data = $request->validate([
            'broker_session_id' => ['required', 'string'],
            'tenant_id' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $result = $broker->selectTenant(
            (string) $data['broker_session_id'],
            (string) $data['tenant_id'],
            $data['device_name'] ?? null,
            $request
        );

        if (!$result) {
            return ApiResponseService::error(
                'BROKER_SESSION_INVALID',
                'INVALID_SESSION',
                null,
                410
            );
        }

        return ApiResponseService::success($result, 'REDIRECT_READY');
    }

    public function exchangeTicket(Request $request, CentralLoginBrokerService $broker)
    {
        $data = $request->validate([
            'ticket' => ['required', 'string', 'min:32'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $result = $broker->redeemTransferTicket(
            (string) $data['ticket'],
            $data['device_name'] ?? null,
            $request
        );

        if (!$result) {
            return ApiResponseService::error(
                'INVALID_TRANSFER_TICKET',
                'INVALID_TICKET',
                null,
                401
            );
        }

        return ApiResponseService::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'abilities' => $result['abilities'],
            'expires_at' => $result['expires_at'],
        ], 'LOGIN_SUCCESS');
    }

    public function forgotPassword(Request $request, TenantPasswordResetService $passwordResetService)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        if (tenancy()->initialized) {
            $passwordResetService->sendResetLinkForCurrentTenant((string) $data['email']);
        } else {
            $passwordResetService->sendResetLinkAcrossActiveTenants((string) $data['email']);
        }

        return ApiResponseService::success(
            null,
            'PASSWORD_RECOVERY_EMAIL_SEND'
        );
    }

    public function resetPassword(Request $request, TenantPasswordResetService $passwordResetService)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'tenant_identifier' => ['sometimes', 'string'],
        ]);

        $tenantIdentifier = $data['tenant_identifier'] ?? null;

        $executeReset = function () use ($data, $passwordResetService) {
            $status = $passwordResetService->resetForCurrentTenant(
                (string) $data['email'],
                (string) $data['token'],
                (string) $data['password'],
            );

            return match ($status) {
                Password::PASSWORD_RESET => ApiResponseService::success(
                    null,
                    'PASSWORD_RECOVERY_RESET_SUCCESS'
                ),
                Password::INVALID_TOKEN => ApiResponseService::error(
                    'INVALID_RESET_TOKEN',
                    'PASSWORD_RECOVERY_INVALID_TOKEN',
                    null,
                    422
                ),
                Password::INVALID_USER => ApiResponseService::error(
                    'INVALID_RESET_USER',
                    'PASSWORD_RECOVERY_INVALID_USER',
                    null,
                    422
                ),
                default => ApiResponseService::error(
                    'PASSWORD_RESET_FAILED',
                    'PASSWORD_RECOVERY_RESET_FAILED',
                    ['status' => $status],
                    422
                ),
            };
        };

        if (tenancy()->initialized) {
            return $executeReset();
        }

        if (!is_string($tenantIdentifier) || $tenantIdentifier === '') {
            return ApiResponseService::validationError([
                'tenant_identifier' => [language()->t('PASSWORD_RECOVERY_TENANT_REQUIRED')],
            ]);
        }

        $tenant = Tenant::query()
            ->where('id', $tenantIdentifier)
            ->orWhere('slug', $tenantIdentifier)
            ->first();

        if (!$tenant) {
            return ApiResponseService::notFound('TENANT_NOT_FOUND');
        }

        tenancy()->initialize($tenant);

        try {
            return $executeReset();
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponseService::success(null, 'LOGOUT_SUCCESS');
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return ApiResponseService::success(null, 'LOGOUT_ALL_DEVICES_SUCCESS');
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $currentToken = $request->user()->currentAccessToken();

        if (!$currentToken) {
            return ApiResponseService::unauthorized('INVALID_TOKEN');
        }

        $tokenName = $currentToken->name ?? 'api-token';
        $abilities = is_array($currentToken->abilities) && $currentToken->abilities !== []
            ? $currentToken->abilities
            : ($user instanceof CentralUser ? ['admin'] : ['tenant-api']);
        $expiresAt = $this->refreshedTokenExpiration($user, $abilities);

        $currentToken->delete();

        $tokenResult = $user->createToken(
            $tokenName,
            $abilities,
            $expiresAt
        );

        return ApiResponseService::success([
            'token' => $tokenResult->plainTextToken,
            'expires_at' => $tokenResult->accessToken->expires_at?->toIso8601String(),
        ], 'TOKEN_RENEWED');
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if ($user instanceof CentralUser && !tenancy()->initialized) {
            return ApiResponseService::success(
                new CentralUserResource($user),
                'USER_RETRIEVED'
            );
        }

        return ApiResponseService::success(
            new UserResource($user),
            'USER_RETRIEVED'
        );
    }

    /**
     * @param  array<int, string>  $abilities
     */
    protected function refreshedTokenExpiration(mixed $user, array $abilities)
    {
        $isAdminToken = $user instanceof CentralUser
            && in_array('admin', $abilities, true);

        return $isAdminToken ? now()->addHours(12) : now()->addDays(7);
    }
}

#[OA\Info(
    version: 'v1',
    title: 'SIGAPP API',
    description: 'API REST para SaaS multi-tenant SIGAPP'
)]
#[OA\Server(url: '/')]
final class OpenApiSpec
{
}
