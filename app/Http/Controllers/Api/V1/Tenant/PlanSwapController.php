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

        $currentPlan = $tenant->plan;
        $isUpgrade = $currentPlan === null
            || $newPlan->getAttribute('sort_order') > $currentPlan->getAttribute('sort_order');

        try {
            if ($isUpgrade) {
                // Upgrade: cobra a diferença proporcional imediatamente no Stripe.
                // Nunca aceitar 'prorate' do cliente para evitar acesso gratuito a planos superiores.
                $subscription->swapAndInvoice($stripePriceId);
                // Aplica o plano imediatamente e cancela qualquer downgrade pendente.
                $tenant->update(['plan_id' => $newPlan->getKey(), 'scheduled_plan_id' => null]);
            } else {
                // Downgrade: muda o preço no Stripe para a próxima renovação.
                // O plan_id permanece inalterado até invoice.paid confirmar o novo ciclo.
                $subscription->swap($stripePriceId);
                $tenant->update(['scheduled_plan_id' => $newPlan->getKey()]);
            }

            cache()->forget('tenant:'.$tenant->getAttribute('slug'));

            $auditMessage = $isUpgrade
                ? "Upgrade para '{$newPlan->getAttribute('name')}' aplicado imediatamente."
                : "Downgrade para '{$newPlan->getAttribute('name')}' agendado para a próxima renovação.";

            $this->audit('tenant.plan_swapped', $auditMessage, [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->getAttribute('slug'),
                'new_plan_id' => $newPlan->getKey(),
                'new_plan_slug' => $newPlan->getAttribute('slug'),
                'is_upgrade' => $isUpgrade,
            ]);

            return ApiResponseService::success([
                'plan' => new PlanResource($newPlan),
                'is_upgrade' => $isUpgrade,
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
