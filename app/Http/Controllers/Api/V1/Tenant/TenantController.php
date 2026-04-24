<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateDefaultPaymentMethodRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\TenantResource;
use App\Models\Tenant\Terreno;
use App\Services\ApiResponseService;
use App\Services\Billing\TenantBillingService;
use App\Services\UsageMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class TenantController extends Controller
{
    public function __construct(
        protected UsageMetricsService $usageService,
        protected TenantBillingService $billingService
    ) {}

    /**
     * Obter informações do tenant atual.
     *
     * GET /api/v1/tenant
     */
    public function show()
    {
        Gate::authorize('viewAny', Terreno::class);

        $tenant = tenancy()->tenant;
        $tenant->load('plan');

        return ApiResponseService::success(
            new TenantResource($tenant),
            language()->t('TENANT_DATA_RETRIEVED')
        );
    }

    /**
     * Obtém métricas de uso do tenant.
     *
     * GET /api/v1/tenant/usage
     */
    public function usage()
    {
        Gate::authorize('viewAny', Terreno::class);

        return ApiResponseService::success([
            'metrics' => $this->usageService->getMetrics(),
            'percentages' => $this->usageService->getUsagePercentages(),
            'approaching_limits' => $this->usageService->isApproachingLimits(),
        ], language()->t('USAGE_METRICS_RETRIEVED'));
    }

    /**
     * Obtém o status da assinatura.
     *
     * GET /api/v1/tenant/subscription
     */
    public function subscription(): JsonResponse
    {
        $tenant = tenancy()->tenant;
        $snapshot = $this->billingService->getSubscriptionSnapshot($tenant);

        return ApiResponseService::success([
            'status' => $tenant->status,
            'plan' => $tenant->plan ? new PlanResource($tenant->plan) : null,
            ...$snapshot,
        ], language()->t('SIGNATURE_DATA_RETRIEVED'));
    }

    /**
     * Cria um Setup Intent do Stripe para atualizar o método de pagamento.
     *
     * O frontend usa o client_secret retornado com o Stripe.js para coletar
     * os novos dados do cartão sem que eles trafeguem pelo servidor.
     *
     * POST /api/v1/tenant/billing/setup-intent
     */
    public function createSetupIntent(): JsonResponse
    {
        $tenant = tenancy()->tenant;

        if (! $tenant->stripe_id) {
            return ApiResponseService::conflict('BILLING_NOT_CONFIGURED');
        }

        try {
            return ApiResponseService::success([
                'client_secret' => $this->billingService->createSetupIntentSecret($tenant),
            ], 'SETUP_INTENT_CREATED');
        } catch (\Exception $e) {
            Log::warning('Erro ao criar Setup Intent', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::error(
                'SETUP_INTENT_ERROR',
                'UNKNOWN_ERROR',
                app()->environment('local') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * Atualiza o método de pagamento padrão do tenant.
     *
     * Recebe o payment_method_id gerado pelo Stripe.js após o Setup Intent ser confirmado.
     *
     * POST /api/v1/tenant/billing/payment-method
     */
    public function updateDefaultPaymentMethod(UpdateDefaultPaymentMethodRequest $request): JsonResponse
    {
        $tenant = tenancy()->tenant;

        try {
            $this->billingService->updateDefaultPaymentMethod(
                $tenant,
                (string) $request->validated('payment_method_id')
            );

            return ApiResponseService::success(null, 'PAYMENT_METHOD_UPDATED');
        } catch (\Exception $e) {
            Log::warning('Erro ao atualizar método de pagamento', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::error(
                'PAYMENT_METHOD_UPDATE_ERROR',
                'UNKNOWN_ERROR',
                app()->environment('local') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * Criar uma sessão do portal de faturamento.
     *
     * GET /api/v1/tenant/billing-portal
     */
    public function billingPortal(): JsonResponse
    {
        $tenant = tenancy()->tenant;

        if (! $tenant->stripe_id) {
            return ApiResponseService::conflict('BILLING_PORTAL_UNAVAILABLE');
        }

        try {
            $returnUrl = rtrim((string) config('app.frontend_url'), '/').'/billing';

            return ApiResponseService::success([
                'url' => $this->billingService->createBillingPortalUrl($tenant, $returnUrl),
            ], 'SUCCESS_OPERATION');
        } catch (\Exception $e) {
            Log::warning('Erro ao criar billing portal', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::error(
                'BILLING_PORTAL_ERROR',
                'UNKNOWN_ERROR',
                app()->environment('local') ? $e->getMessage() : null,
                500
            );
        }
    }
}
