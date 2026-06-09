<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeTicketRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\CentralUserResource;
use App\Http\Resources\UserResource;
use App\Models\User as CentralUser;
use App\Services\ApiResponseService;
use App\Services\Auth\CentralLoginBrokerService;
use App\Services\Auth\TenantLoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantAuthController extends Controller
{
    /**
     * Realiza o login no tenant atual.
     */
    public function login(LoginRequest $request, TenantLoginService $tenantLogin): JsonResponse
    {
        return $this->respondToTenantLogin(
            $tenantLogin->attempt($request->validated(), $request->validated('device_name'))
        );
    }

    /**
     * Troca um ticket emitido pelo broker central por token de acesso no tenant.
     */
    public function exchangeTicket(ExchangeTicketRequest $request, CentralLoginBrokerService $broker): JsonResponse
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
     * Revoga apenas o token atual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponseService::success(null, 'LOGOUT_SUCCESS');
    }

    /**
     * Revoga todos os tokens do usuário autenticado.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return ApiResponseService::success(null, 'LOGOUT_ALL_DEVICES_SUCCESS');
    }

    /**
     * Renova o token atual preservando as mesmas abilities.
     */
    public function refresh(Request $request, TenantLoginService $tenantLogin): JsonResponse
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

    /**
     * Retorna o usuário autenticado no contexto atual.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof CentralUser && ! tenancy()->initialized) {
            return ApiResponseService::success(new CentralUserResource($user), 'USER_RETRIEVED');
        }

        return ApiResponseService::success(new UserResource($user), 'USER_RETRIEVED');
    }

    /**
     * Atualiza o perfil do próprio usuário autenticado.
     */
    public function updateMe(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $password = $validated['password'] ?? null;
        unset($validated['password']);

        $user->fill($validated);

        if ($password !== null) {
            $user->password = $password; // cast 'hashed' no model cuida do bcrypt
        }

        $user->save();

        return ApiResponseService::success(new UserResource($user->fresh('roles')), 'USER_UPDATED_SUCCESSFULLY');
    }

    /**
     * Constrói a resposta HTTP a partir do resultado do serviço de login tenant.
     *
     * @param  array{success: false}|array{success: true, user: \App\Models\Tenant\User, token: string, abilities: list<string>, expires_at: string|null}  $result
     */
    private function respondToTenantLogin(array $result): JsonResponse
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
