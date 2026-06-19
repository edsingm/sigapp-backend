<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
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
        if (! $tenant instanceof Tenant) {
            return ApiResponseService::serverError('TENANT_CONTEXT_NOT_AVAILABLE');
        }

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
        if (! $tenant instanceof Tenant) {
            return ApiResponseService::serverError('TENANT_CONTEXT_NOT_AVAILABLE');
        }

        if ((string) $tenant->getAttribute('status') === TenantStatus::CANCELLED->value) {
            return ApiResponseService::conflict('ACCOUNT_CANCELLED');
        }

        $invoiceUrl = $this->billingService->getOpenInvoicePaymentUrl($tenant);

        if ($invoiceUrl === null) {
            return ApiResponseService::error(
                'PAYMENT_RETRY_ERROR',
                'PAYMENT_RETRY_FAILED',
                null,
                422
            );
        }

        // Direciona o cliente à página hospedada do Stripe para concluir o pagamento
        // (cobra/atualiza cartão e resolve SCA nativamente).
        return ApiResponseService::success(
            ['invoice_url' => $invoiceUrl],
            language()->t('PAYMENT_RETRY_INITIATED')
        );
    }
}
