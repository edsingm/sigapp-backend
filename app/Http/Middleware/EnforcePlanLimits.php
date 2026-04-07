<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use App\Services\PlanMatrixService;
use App\Services\UsageMetricsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class EnforcePlanLimits
{
    public function __construct(
        protected UsageMetricsService $usageService,
        protected PlanMatrixService $planMatrix
    ) {}

    /**
     * Manipula uma requisição de entrada.
     */
    public function handle(Request $request, Closure $next, ?string $resource = null): Response
    {
        // Verifica apenas em requisições POST (criação de recursos)
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        // Ignora se não houver contexto de tenant
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
        $tenant = tenancy()->tenant;
        $plan = $tenant?->plan;

        if (! $plan || ! $tenant) {
            return true;
        }

        return match ($resource) {
            'users' => ! $this->planMatrix->isUnlimitedLimitForTenant($tenant, 'users')
                && $this->usageService->getUserCount() >= $this->planMatrix->getLimitForTenant($tenant, 'users'),
            'terrenos' => ! $this->planMatrix->isUnlimitedLimitForTenant($tenant, 'terrenos')
                && $this->usageService->getTerrenoCount() >= $this->planMatrix->getLimitForTenant($tenant, 'terrenos'),
            'products' => ! $this->planMatrix->isUnlimitedLimitForTenant($tenant, 'products')
                && $this->usageService->getProdutoCount() >= $this->planMatrix->getLimitForTenant($tenant, 'products'),
            'storage', 'storage_gb' => $this->storageLimitExceeded($request),
            default => false,
        };
    }

    protected function storageLimitExceeded(Request $request): bool
    {
        $tenant = tenancy()->tenant;
        $plan = $tenant?->plan;

        if (! $plan || ! $tenant) {
            return true;
        }

        if ($this->planMatrix->isUnlimitedLimitForTenant($tenant, 'storage_gb')) {
            return false;
        }

        $maxStorageGb = $this->planMatrix->getLimitForTenant($tenant, 'storage_gb');
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

            if ($file instanceof UploadedFile) {
                $total += (int) $file->getSize();
            }
        }

        return $total;
    }
}
