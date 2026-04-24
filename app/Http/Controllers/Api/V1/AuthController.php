<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeTicketRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SelectTenantRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\CentralUserResource;
use App\Http\Resources\UserResource;
use App\Models\User as CentralUser;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\ApiResponseService;
use App\Services\Auth\CentralLoginBrokerService;
use App\Services\Auth\TenantLoginService;
use App\Services\Auth\TenantPasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}
    /**
     * Realizar o login do usuário.
     */
    public function login(LoginRequest $request, CentralLoginBrokerService $broker, TenantLoginService $tenantLogin)
    {
        if (! tenancy()->initialized) {
            $tenantIdentifier = $tenantLogin->resolveLocalTenantIdentifier($request);

            if ($tenantIdentifier !== null) {
                $tenant = $this->tenantRepository->findByIdOrSlug($tenantIdentifier);

                if (! $tenant) {
                    return ApiResponseService::notFound('TENANT_NOT_FOUND');
                }

                tenancy()->initialize($tenant);

                try {
                    return $this->respondToTenantLogin(
                        $tenantLogin->attempt($request->validated(), $request)
                    );
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
                return ApiResponseService::error('UNAUTHORIZED', 'INVALID_CREDENTIALS', null, 401);
            }

            return ApiResponseService::success(
                $result,
                ($result['next_action'] ?? null) === 'choose_tenant' ? 'CHOOSE_TENANT' : 'REDIRECT_READY'
            );
        }

        return $this->respondToTenantLogin($tenantLogin->attempt($request->validated(), $request));
    }

    /**
     * Selecionar um tenant após o login central.
     */
    public function selectTenant(SelectTenantRequest $request, CentralLoginBrokerService $broker)
    {
        $data = $request->validated();

        $result = $broker->selectTenant(
            (string) $data['broker_session_id'],
            (string) $data['tenant_id'],
            $data['device_name'] ?? null,
            $request
        );

        if (! $result) {
            return ApiResponseService::error('BROKER_SESSION_INVALID', 'INVALID_SESSION', null, 410);
        }

        return ApiResponseService::success($result, 'REDIRECT_READY');
    }

    /**
     * Trocar um ticket por um token de acesso.
     */
    public function exchangeTicket(ExchangeTicketRequest $request, CentralLoginBrokerService $broker)
    {
        $data = $request->validated();

        $result = $broker->redeemTransferTicket(
            (string) $data['ticket'],
            $data['device_name'] ?? null,
            $request
        );

        if (! $result) {
            return ApiResponseService::error('INVALID_TRANSFER_TICKET', 'INVALID_TICKET', null, 401);
        }

        return ApiResponseService::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'abilities' => $result['abilities'],
            'expires_at' => $result['expires_at'],
        ], 'LOGIN_SUCCESS');
    }

    /**
     * Enviar link de recuperação de senha.
     */
    public function forgotPassword(ForgotPasswordRequest $request, TenantPasswordResetService $passwordResetService)
    {
        $data = $request->validated();

        if (tenancy()->initialized) {
            $passwordResetService->sendResetLinkForCurrentTenant((string) $data['email']);
        } else {
            $passwordResetService->sendResetLinkAcrossActiveTenants((string) $data['email']);
        }

        return ApiResponseService::success(null, 'PASSWORD_RECOVERY_EMAIL_SEND');
    }

    /**
     * Redefinir a senha do usuário.
     */
    public function resetPassword(ResetPasswordRequest $request, TenantPasswordResetService $passwordResetService)
    {
        $data = $request->validated();

        $tenantIdentifier = $data['tenant_identifier'] ?? null;

        $executeReset = function () use ($data, $passwordResetService) {
            $status = $passwordResetService->resetForCurrentTenant(
                (string) $data['email'],
                (string) $data['token'],
                (string) $data['password'],
            );

            return match ($status) {
                Password::PASSWORD_RESET => ApiResponseService::success(null, 'PASSWORD_RECOVERY_RESET_SUCCESS'),
                Password::INVALID_TOKEN => ApiResponseService::error('INVALID_RESET_TOKEN', 'PASSWORD_RECOVERY_INVALID_TOKEN', null, 422),
                Password::INVALID_USER => ApiResponseService::error('INVALID_RESET_USER', 'PASSWORD_RECOVERY_INVALID_USER', null, 422),
                default => ApiResponseService::error('PASSWORD_RESET_FAILED', 'PASSWORD_RECOVERY_RESET_FAILED', ['status' => $status], 422),
            };
        };

        if (tenancy()->initialized) {
            return $executeReset();
        }

        if (! is_string($tenantIdentifier) || $tenantIdentifier === '') {
            return ApiResponseService::validationError([
                'tenant_identifier' => [language()->t('PASSWORD_RECOVERY_TENANT_REQUIRED')],
            ]);
        }

        $tenant = $this->tenantRepository->findByIdOrSlug($tenantIdentifier);

        if (! $tenant) {
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
     * Realizar o logout do usuário (apenas o token atual).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponseService::success(null, 'LOGOUT_SUCCESS');
    }

    /**
     * Realizar o logout do usuário em todos os dispositivos.
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return ApiResponseService::success(null, 'LOGOUT_ALL_DEVICES_SUCCESS');
    }

    public function refresh(Request $request, TenantLoginService $tenantLogin)
    {
        $user = $request->user();
        $currentToken = $request->user()->currentAccessToken();

        if (! $currentToken) {
            return ApiResponseService::unauthorized('INVALID_TOKEN');
        }

        $tokenName = $currentToken->name ?? 'api-token';
        $abilities = is_array($currentToken->abilities) && $currentToken->abilities !== []
            ? $currentToken->abilities
            : ($user instanceof CentralUser ? ['admin'] : ['tenant-api']);

        $expiresAt = $tenantLogin->tokenExpiration($user, $abilities);

        $currentToken->delete();

        $tokenResult = $user->createToken($tokenName, $abilities, $expiresAt);

        return ApiResponseService::success([
            'token' => $tokenResult->plainTextToken,
            'expires_at' => $tokenResult->accessToken->expires_at?->toIso8601String(),
        ], 'TOKEN_RENEWED');
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if ($user instanceof CentralUser && ! tenancy()->initialized) {
            return ApiResponseService::success(new CentralUserResource($user), 'USER_RETRIEVED');
        }

        return ApiResponseService::success(new UserResource($user), 'USER_RETRIEVED');
    }

    /**
     * Atualiza o perfil do próprio usuário autenticado (autoatendimento).
     *
     * Qualquer usuário autenticado pode atualizar nome, e-mail, locale e senha
     * da sua própria conta, sem necessidade de papel administrativo.
     *
     * PUT /api/v1/auth/me
     */
    public function updateMe(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->fill(collect($validated)->except('password')->toArray());

        if (isset($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return ApiResponseService::success(new UserResource($user->fresh('roles')), 'USER_UPDATED_SUCCESSFULLY');
    }

    /**
     * Constrói uma resposta HTTP a partir do resultado de TenantLoginService::attempt().
     */
    private function respondToTenantLogin(array $result)
    {
        if (! $result['success']) {
            return ApiResponseService::error('UNAUTHORIZED', 'INVALID_CREDENTIALS', null, 401);
        }

        return ApiResponseService::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'abilities' => $result['abilities'],
            'expires_at' => $result['expires_at'],
        ], 'LOGIN_SUCCESS');
    }
}

#[OA\Info(
    version: 'v1',
    title: 'SIGAPP API',
    description: 'API REST para SaaS multi-tenant SIGAPP'
)]
#[OA\Server(url: '/')]
final class OpenApiSpec {}
