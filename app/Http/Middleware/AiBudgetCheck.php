<?php

namespace App\Http\Middleware;

use App\Services\AiTelemetryService;
use App\Services\ApiResponseService;
use App\Services\PlanMatrixService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiBudgetCheck
{
    public function __construct(
        protected AiTelemetryService $telemetryService,
        protected PlanMatrixService $planMatrix
    ) {}

    /**
     * Manipula uma requisição de entrada.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            return $next($request);
        }

        $tenant = tenancy()->tenant;
        $plan = $tenant?->plan;

        if (! $plan) {
            return ApiResponseService::error(
                'NO_PLAN',
                'Tenant não possui plano ativo',
                null,
                403
            );
        }

        // Resolve budget do plano (entitlement ai_budget) ou usa default
        $budget = $this->resolveBudget();
        $budgetStatus = $this->telemetryService->getBudgetStatus();

        if ($budgetStatus['exceeded']) {
            return ApiResponseService::error(
                'AI_BUDGET_EXCEEDED',
                'O orçamento mensal de IA foi excedido. Faça upgrade do plano ou aguarde o próximo ciclo.',
                [
                    'budget_usd' => number_format($budgetStatus['budget_usd'], 2),
                    'spent_usd' => number_format($budgetStatus['spent_usd'], 6),
                    'usage_percent' => $budgetStatus['usage_percent'],
                ],
                402,
            );
        }

        return $next($request);
    }

    /**
     * Resolve o orçamento configurável para o tenant.
     */
    protected function resolveBudget(): float
    {
        $default = (float) env('AI_TENANT_BUDGET_DEFAULT', 10.00);

        try {
            if (! tenancy()->initialized) {
                return $default;
            }

            $tenant = tenancy()->tenant;
            $custom = $this->planMatrix->getLimitForTenant($tenant, 'ai_budget');

            if ($custom > 0) {
                return (float) $custom;
            }
        } catch (\Throwable) {
            // Fallback ao default se não resolver via PlanMatrix
        }

        return $default;
    }
}
