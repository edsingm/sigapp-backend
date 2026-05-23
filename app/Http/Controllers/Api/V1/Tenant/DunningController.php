<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\Billing\TenantBillingService;
use Illuminate\Http\JsonResponse;

class DunningController extends Controller
{
    public function __construct(
        protected TenantBillingService $billingService
    ) {}

    /**
     * Retorna o status de pagamento do tenant (dunning).
     *
     * GET /api/v1/tenant/billing/payment-status
     */
    public function status(): JsonResponse
    {
        $tenant = tenancy()->tenant;

        $status = $this->billingService->getPaymentRetryStatus($tenant);

        return ApiResponseService::success($status, language()->t('PAYMENT_STATUS_RETRIEVED'));
    }

    /**
     * Dispara o reprocessamento de pagamento pendente.
     *
     * POST /api/v1/tenant/billing/retry-payment
     */
    public function retryPayment(): JsonResponse
    {
        $tenant = tenancy()->tenant;

        if ($tenant->status === TenantStatus::CANCELLED->value) {
            return ApiResponseService::conflict('ACCOUNT_CANCELLED');
        }

        $success = $this->billingService->triggerPaymentRetry($tenant);

        if (! $success) {
            return ApiResponseService::error(
                'PAYMENT_RETRY_ERROR',
                'PAYMENT_RETRY_FAILED',
                null,
                422
            );
        }

        return ApiResponseService::success(null, language()->t('PAYMENT_RETRY_INITIATED'));
    }
}
