<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\SelectTenantRequest;
use App\Http\Resources\UserResource;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\ApiResponseService;
use App\Services\Auth\CentralLoginBrokerService;
use App\Services\Auth\TenantLoginService;
use Illuminate\Http\JsonResponse;

class CentralAuthController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Realiza o login central e devolve descoberta de tenant ou redirecionamento.
     */
    public function login(LoginRequest $request, CentralLoginBrokerService $broker, TenantLoginService $tenantLogin): JsonResponse
    {
        $tenantIdentifier = $tenantLogin->resolveLocalTenantIdentifier($request);

        if ($tenantIdentifier !== null) {
            $tenant = $this->tenantRepository->findByIdOrSlug($tenantIdentifier);

            if (! $tenant) {
                return ApiResponseService::notFound('TENANT_NOT_FOUND');
            }

            tenancy()->initialize($tenant);

            try {
                $result = $tenantLogin->attempt($request->validated(), $request->validated('device_name'));

                if (! $result['success']) {
                    return ApiResponseService::error('UNAUTHORIZED', 'INVALID_CREDENTIALS', null, 401);
                }

                return ApiResponseService::success([
                    'user' => new UserResource($result['user']),
                    'token' => $result['token'],
                    'abilities' => $result['abilities'],
                    'expires_at' => $result['expires_at'],
                ], 'LOGIN_SUCCESS');
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

    /**
     * Seleciona um tenant após múltiplos matches no broker central.
     */
    public function selectTenant(SelectTenantRequest $request, CentralLoginBrokerService $broker): JsonResponse
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
}
