<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\CentralUserResource;
use App\Http\Resources\UserResource;
use App\Models\User as CentralUser;
use App\Services\Auth\CentralLoginBrokerService;
use App\Services\Auth\TenantPasswordResetService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    /**
     * Login user with tenant specification (central context).
     *
     * POST /api/v1/auth/login-tenant
     */
    public function loginWithTenant(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'tenant_identifier' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $tenant = Tenant::where('id', $credentials['tenant_identifier'])
            ->orWhere('slug', $credentials['tenant_identifier'])
            ->first();

        if (!$tenant) {
            return ApiResponseService::error(
                'TENANT_NOT_FOUND',
                'TENANT_NOT_FOUND',
                null,
                404
            );
        }

        tenancy()->initialize($tenant);

        try {
            $user = \App\Models\Tenant\User::where('email', $credentials['email'])->first();

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
                $credentials['device_name'] ?? 'api-token',
                ['*'],
                now()->addDays(7)
            );

            return ApiResponseService::success([
                'user' => new UserResource($user),
                'token' => $tokenResult->plainTextToken,
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ],
                'abilities' => ['*'],
                'expires_at' => $tokenResult->accessToken->expires_at?->toIso8601String(),
            ], 'LOGIN_SUCCESS');
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    /**
     * Central login broker: authenticate by email+password and return redirect/select response.
     *
     * POST /api/v1/auth/central-login
     */
    public function centralLogin(Request $request, CentralLoginBrokerService $broker)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $result = $broker->attemptCentralLogin(
            (string) $credentials['email'],
            (string) $credentials['password'],
            $credentials['device_name'] ?? null,
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

    /**
     * Central login broker: choose tenant after multi-tenant auth success.
     *
     * POST /api/v1/auth/central-login/select-tenant
     */
    public function selectCentralLoginTenant(Request $request, CentralLoginBrokerService $broker)
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

    /**
     * Redeem a central transfer ticket in tenant context.
     *
     * POST /api/v1/auth/redeem-transfer-ticket
     */
    public function redeemTransferTicket(Request $request, CentralLoginBrokerService $broker)
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

    /**
     * Login user and return token (tenant context).
     *
     * POST /api/v1/auth/login
     */
    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Login do usuário (tenant)',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Success'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 410, description: 'Deprecated endpoint'),
        ]
    )]

    public function login(LoginRequest $request)
    {
        if (!tenancy()->initialized) {
            return ApiResponseService::error(
                'DEPRECATED_ENDPOINT',
                'USE_CENTRAL_LOGIN_FLOW',
                null,
                410
            );
        }

        $credentials = $request->validated();


        // Find user by email in tenant context
        $user = \App\Models\Tenant\User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return ApiResponseService::error(
                'UNAUTHORIZED',
                'INVALID_CREDENTIALS',
                null,
                401
            );
        }

        // Revoke previous tokens for this device (optional)
        if ($request->has('device_name')) {
            $user->tokens()->where('name', $credentials['device_name'])->delete();
        }

        // Create new token
        $tokenResult = $user->createToken(
            $credentials['device_name'] ?? 'api-token',
            ['*'], // Abilities
            now()->addDays(7) // Expires in 7 days
        );

        return ApiResponseService::success([
            'user' => new UserResource($user),
            'token' => $tokenResult->plainTextToken,
            'abilities' => ['*'],
            'expires_at' => $tokenResult->accessToken->expires_at?->toIso8601String(),
        ], 'LOGIN_SUCCESS');
    }

    /**
     * Logout user (revoke current token).
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request)
    {
        // Revoke the token that was used for authentication
        $request->user()->currentAccessToken()->delete();

        return ApiResponseService::success(null, 'LOGOUT_SUCCESS');
    }

    /**
     * Logout from all devices.
     *
     * POST /api/v1/auth/logout-all
     */
    public function logoutAll(Request $request)
    {
        // Revoke all tokens
        $request->user()->tokens()->delete();

        return ApiResponseService::success(null, 'LOGOUT_ALL_DEVICES_SUCCESS');
    }

    /**
     * Refresh token.
     *
     * POST /api/v1/auth/refresh
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        $currentToken = $request->user()->currentAccessToken();

        if (!$currentToken) {
            return ApiResponseService::unauthorized('INVALID_TOKEN');
        }

        $tokenName = $currentToken?->name ?? 'api-token';
        $abilities = is_array($currentToken?->abilities) && $currentToken->abilities !== []
            ? $currentToken->abilities
            : ['*'];
        $expiresAt = $this->refreshedTokenExpiration($user, $abilities);

        // Revoke current token
        $currentToken->delete();

        // Create new token
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

    /**
     * Get current authenticated user.
     *
     * GET /api/v1/auth/me
     */
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
            && (in_array('admin', $abilities, true) || in_array('*', $abilities, true));

        return $isAdminToken ? now()->addHours(12) : now()->addDays(7);
    }
}

/**
 * Define os metadados globais da especificação OpenAPI da API SIGPRO.
 *
 * Esta classe atua como um ponto de ancoragem para os atributos `OA\Info` e `OA\Server`,
 * informando versão, título, descrição e URL base da documentação da API.
 */
#[OA\Info(
    version: 'v1',
    title: 'SIGPRO API',
    description: 'API REST para SaaS multi-tenant SIGPRO'
)]
#[OA\Server(url: '/')]
final class OpenApiSpec {}
