<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use App\Services\UsageMetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePlanLimits
{
    public function __construct(
        protected UsageMetricsService $usageService
    ) {
    }

    /**
     * Manipula uma requisição de entrada.
     */
    public function handle(Request $request, Closure $next, string $resource = null): Response
    {
        // Verifica apenas em requisições POST (criação de recursos)
        if (!$request->isMethod('POST')) {
            return $next($request);
        }

        // Ignora se não houver contexto de tenant
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

        // Verifica limites específicos do recurso
        if ($resource) {
            $limitExceeded = $this->checkResourceLimit($resource, $request);

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
     * Verifica se o limite de um recurso foi excedido.
     */
    protected function checkResourceLimit(string $resource, Request $request): bool
    {
        $plan = tenancy()->tenant?->plan;

        if (!$plan) {
            return true;
        }

        return match ($resource) {
            'users' => !$plan->hasUnlimitedLimit('users')
                && $this->usageService->getUserCount() >= $plan->getLimit('users'),
            'terrenos' => !$plan->hasUnlimitedLimit('terrenos')
                && $this->usageService->getTerrenoCount() >= $plan->getLimit('terrenos'),
            'products' => !$plan->hasUnlimitedLimit('products')
                && $this->usageService->getProdutoCount() >= $plan->getLimit('products'),
            'storage', 'storage_gb' => $this->storageLimitExceeded($request),
            default => false,
        };
    }

    protected function storageLimitExceeded(Request $request): bool
    {
        $plan = tenancy()->tenant?->plan;

        if (!$plan) {
            return true;
        }

        if ($plan->hasUnlimitedLimit('storage_gb')) {
            return false;
        }

        $maxStorageGb = $plan->getLimit('storage_gb');
        $maxStorageBytes = $maxStorageGb * 1024 * 1024 * 1024;
        $incomingBytes = $this->incomingUploadBytes($request->allFiles());

        return ($this->usageService->getStorageUsedBytes() + $incomingBytes) > $maxStorageBytes;
    }

    /**
     * @param  array<string, mixed>  $files
     */
    protected function incomingUploadBytes(array $files): int
    {
        $total = 0;

        foreach ($files as $file) {
            if (is_array($file)) {
                $total += $this->incomingUploadBytes($file);
                continue;
            }

            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $total += (int) $file->getSize();
            }
        }

        return $total;
    }
}
