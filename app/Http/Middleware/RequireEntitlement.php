<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use App\Services\PlanEntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireEntitlement
{
    public function __construct(
        protected PlanEntitlementService $entitlements
    ) {
    }

    /**
     * Middleware params:
     * - key
     * - expected (optional; defaults to true)
     * - comparison (optional: exact|min, defaults to exact)
     */
    public function handle(Request $request, Closure $next, string $key, ?string $expected = null, string $comparison = 'exact'): Response
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

        $expectedValue = $this->normalizeExpected($expected);
        $comparison = strtolower(trim($comparison));

        $allowed = match ($comparison) {
            'min', 'gte' => $this->entitlements->meetsMinimum($key, $expectedValue, $plan),
            default => $this->entitlements->matches($key, $expectedValue, $plan),
        };

        if (!$allowed) {
            return ApiResponseService::error(
                'PLAN_ENTITLEMENT_DISABLED',
                'Recurso indisponível para o plano atual.',
                [
                    'entitlement' => $key,
                    'required' => $expectedValue,
                    'comparison' => $comparison,
                    'plan' => $plan->slug,
                ],
                403
            );
        }

        return $next($request);
    }

    protected function normalizeExpected(?string $expected): mixed
    {
        if ($expected === null || $expected === '') {
            return true;
        }

        $normalized = strtolower(trim($expected));

        return match ($normalized) {
            'true', '1', 'yes', 'on' => true,
            'false', '0', 'no', 'off' => false,
            default => $expected,
        };
    }
}
