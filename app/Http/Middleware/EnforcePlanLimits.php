<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use App\Services\TenantFeatureService;
use App\Services\UsageMetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePlanLimits
{
    public function __construct(
        protected TenantFeatureService $featureService,
        protected UsageMetricsService $usageService
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $resource = null): Response
    {
        // Only check on POST requests (creating resources)
        if (!$request->isMethod('POST')) {
            return $next($request);
        }

        // Skip if no tenant context
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

        // Check resource-specific limits
        if ($resource) {
            $limitExceeded = $this->checkResourceLimit($resource);

            if ($limitExceeded) {
                return ApiResponseService::error(
                    'PLAN_LIMIT_EXCEEDED',
                    "Limite do plano atingido para {$resource}. Faça upgrade para continuar.",
                    [
                        'resource' => $resource,
                        'plan' => $plan->name,
                        'upgrade_url' => '/api/v1/tenant/subscription/upgrade',
                    ],
                    403
                );
            }
        }

        return $next($request);
    }

    /**
     * Check if a resource limit is exceeded.
     */
    protected function checkResourceLimit(string $resource): bool
    {
        return match ($resource) {
            'users' => !$this->featureService->canCreateUsers($this->usageService->getUserCount()),
            'terrenos' => !$this->featureService->canCreateTerrenos($this->usageService->getTerrenoCount()),
            'storage' => $this->featureService->isStorageExceeded($this->usageService->getStorageUsed()),
            default => false,
        };
    }
}
