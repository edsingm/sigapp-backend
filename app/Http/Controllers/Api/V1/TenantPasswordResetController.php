<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\ApiResponseService;
use App\Services\Auth\TenantPasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class TenantPasswordResetController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Dispara o fluxo de recuperação de senha no tenant atual ou no broker central.
     */
    public function forgotPassword(ForgotPasswordRequest $request, TenantPasswordResetService $passwordResetService): JsonResponse
    {
        if (tenancy()->initialized) {
            $status = $passwordResetService->sendResetLinkForCurrentTenant(
                (string) $request->validated('email')
            );
        } else {
            $status = $passwordResetService->sendResetLinkAcrossActiveTenants(
                (string) $request->validated('email')
            );
        }

        return ApiResponseService::success([
            'status' => $status,
        ], 'PASSWORD_RESET_LINK_SENT');
    }

    /**
     * Redefine a senha do usuário autenticando pelo broker correto.
     */
    public function resetPassword(ResetPasswordRequest $request, TenantPasswordResetService $passwordResetService): JsonResponse
    {
        $validated = $request->validated();

        if (! tenancy()->initialized) {
            $tenantIdentifier = $validated['tenant_identifier'] ?? null;

            if (! is_string($tenantIdentifier) || $tenantIdentifier === '') {
                return ApiResponseService::validationError([
                    'tenant_identifier' => ['The tenant identifier field is required.'],
                ]);
            }

            $tenant = $this->tenantRepository->findByIdOrSlug($tenantIdentifier);

            if (! $tenant) {
                return ApiResponseService::notFound('TENANT_NOT_FOUND');
            }

            tenancy()->initialize($tenant);

            try {
                $status = $passwordResetService->resetForCurrentTenant(
                    (string) $validated['email'],
                    (string) $validated['token'],
                    (string) $validated['password'],
                );
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        } else {
            $status = $passwordResetService->resetForCurrentTenant(
                (string) $validated['email'],
                (string) $validated['token'],
                (string) $validated['password'],
            );
        }

        return match ($status) {
            Password::PASSWORD_RESET => ApiResponseService::success(['status' => $status], 'PASSWORD_RESET_SUCCESS'),
            Password::INVALID_TOKEN => ApiResponseService::error('INVALID_RESET_TOKEN', 'PASSWORD_RECOVERY_INVALID_TOKEN', null, 422),
            Password::INVALID_USER => ApiResponseService::error('INVALID_RESET_USER', 'PASSWORD_RECOVERY_INVALID_USER', null, 422),
            default => ApiResponseService::error('PASSWORD_RESET_FAILED', 'PASSWORD_RECOVERY_RESET_FAILED', ['status' => $status], 422),
        };
    }
}
