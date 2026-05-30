<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\PlanSwapRequest;
use App\Http\Resources\PlanResource;
use App\Models\Central\Tenant;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Services\ApiResponseService;
use App\Services\Billing\TenantBillingService;
use App\Traits\LogsAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PlanSwapController extends Controller
{
    use LogsAudit;

    public function __construct(
        protected TenantBillingService $billingService,
        private readonly PlanRepositoryInterface $planRepository,
    ) {}

    /**
     * Troca o plano da assinatura ativa do tenant.
     *
     * - prorate=true (padrão): cobra imediatamente a diferença proporcional (ideal para upgrades).
     * - prorate=false: aplica a mudança apenas no próximo ciclo de cobrança (ideal para downgrades).
     *
     * POST /api/v1/tenant/subscription/swap
     */
    public function swap(PlanSwapRequest $request): JsonResponse
    {
        $tenant = tenancy()->tenant;
        if (! $tenant instanceof Tenant) {
            return ApiResponseService::serverError('TENANT_CONTEXT_NOT_AVAILABLE');
        }

        $newPlan = $this->planRepository->findActiveBySlug($request->validated('plan_slug'));

        if (! $newPlan) {
            return ApiResponseService::notFound('PLAN_NOT_FOUND');
        }

        $stripePriceId = $newPlan->getAttribute('stripe_price_id');

        if (! is_string($stripePriceId) || $stripePriceId === '') {
            return ApiResponseService::error('PLAN_UNAVAILABLE', 'PLAN_UNAVAILABLE', null, 422);
        }

        $subscription = $tenant->subscription('default');

        if (! $subscription || ! $subscription->active()) {
            return ApiResponseService::conflict('NO_ACTIVE_SUBSCRIPTION');
        }

        if ((string) $subscription->getAttribute('stripe_price') === $stripePriceId) {
            return ApiResponseService::conflict('ALREADY_ON_THIS_PLAN');
        }

        $prorate = (bool) $request->validated('prorate', true);

        try {
            if ($prorate) {
                // swapAndInvoice: cobra imediatamente o valor proporcional (upgrade)
                $subscription->swapAndInvoice($stripePriceId);
            } else {
                // swap: aplica no próximo ciclo sem cobrança imediata (downgrade)
                $subscription->swap($stripePriceId);
            }

            $tenant->update(['plan_id' => $newPlan->getKey()]);
            cache()->forget('tenant:'.$tenant->getAttribute('slug'));

            $this->audit('tenant.plan_swapped', "Plano alterado para '{$newPlan->getAttribute('name')}'.", [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->getAttribute('slug'),
                'new_plan_id' => $newPlan->getKey(),
                'new_plan_slug' => $newPlan->getAttribute('slug'),
                'prorate' => $prorate,
            ]);

            return ApiResponseService::success([
                'plan' => new PlanResource($newPlan),
                'prorate' => $prorate,
            ], 'PLAN_CHANGED_SUCCESSFULLY');

        } catch (\Exception $e) {
            Log::error('Erro ao trocar plano da assinatura', [
                'tenant_id' => $tenant->id,
                'new_plan_slug' => $newPlan->getAttribute('slug'),
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::error(
                'PLAN_SWAP_ERROR',
                'UNKNOWN_ERROR',
                app()->environment('local') ? $e->getMessage() : null,
                500
            );
        }
    }
}
