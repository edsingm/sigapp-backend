<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\PlanSwapRequest;
use App\Http\Resources\PlanResource;
use App\Models\Central\Plan;
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
        abort_unless(auth()->user()?->isAdmin(), 403);

        $tenant = tenancy()->tenant;

        $newPlan = Plan::where('slug', $request->validated('plan_slug'))
            ->active()
            ->first();

        if (! $newPlan) {
            return ApiResponseService::notFound('PLAN_NOT_FOUND');
        }

        if (! $newPlan->stripe_price_id) {
            return ApiResponseService::error('PLAN_UNAVAILABLE', 'PLAN_UNAVAILABLE', null, 422);
        }

        $subscription = $tenant->subscription('default');

        if (! $subscription || ! $subscription->active()) {
            return ApiResponseService::conflict('NO_ACTIVE_SUBSCRIPTION');
        }

        if ($subscription->stripe_price === $newPlan->stripe_price_id) {
            return ApiResponseService::conflict('ALREADY_ON_THIS_PLAN');
        }

        $prorate = (bool) $request->validated('prorate', true);

        try {
            if ($prorate) {
                // swapAndInvoice: cobra imediatamente o valor proporcional (upgrade)
                $subscription->swapAndInvoice($newPlan->stripe_price_id);
            } else {
                // swap: aplica no próximo ciclo sem cobrança imediata (downgrade)
                $subscription->swap($newPlan->stripe_price_id);
            }

            $tenant->update(['plan_id' => $newPlan->id]);

            $this->audit('tenant.plan_swapped', "Plano alterado para '{$newPlan->name}'.", [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'new_plan_id' => $newPlan->id,
                'new_plan_slug' => $newPlan->slug,
                'prorate' => $prorate,
            ]);

            return ApiResponseService::success([
                'plan' => new PlanResource($newPlan),
                'prorate' => $prorate,
            ], 'PLAN_CHANGED_SUCCESSFULLY');

        } catch (\Exception $e) {
            Log::error('Erro ao trocar plano da assinatura', [
                'tenant_id' => $tenant->id,
                'new_plan_slug' => $newPlan->slug,
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
