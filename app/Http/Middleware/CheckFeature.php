<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use App\Services\PlanMatrixService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeature
{
    public function __construct(
        protected PlanMatrixService $planMatrix
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (!tenancy()->initialized) {
            return $next($request);
        }

        $tenant = tenancy()->tenant;
        $plan = $tenant?->plan;

        if (!$plan) {
            return ApiResponseService::error(
                'NO_PLAN',
                'Tenant não possui plano ativo',
                null,
                403
            );
        }

        if (!$this->planMatrix->hasFeature($plan, $feature)) {
            return ApiResponseService::error(
                'PLAN_FEATURE_DISABLED',
                'Seu plano atual não permite esta funcionalidade.',
                [
                    'feature' => $feature,
                    'plan' => $plan->slug,
                ],
                403
            );
        }

        return $next($request);
    }
}
