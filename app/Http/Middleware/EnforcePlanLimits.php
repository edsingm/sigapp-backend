<?php

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use App\Services\ApiResponseService;
use App\Services\PlanMatrixService;
use App\Services\UsageMetricsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnforcePlanLimits
{
    private const RESOURCE_LOCK_SECONDS = 130;

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

        if (! $resource) {
            return $next($request);
        }

        $canonicalResource = $resource === 'storage' ? 'storage_gb' : $resource;
        $protectedResources = ['users', 'terrenos', 'products', 'storage_gb'];

        if (! in_array($canonicalResource, $protectedResources, true)) {
            return $this->continueWhenAllowed($request, $next, $canonicalResource, $plan->name);
        }

        if (! $tenant instanceof Tenant) {
            return ApiResponseService::error('NO_PLAN', 'Tenant inválido.', null, 403);
        }

        // Storage usa este middleware apenas para rejeição antecipada. A verificação
        // definitiva e a persistência ocorrem sob lock em StorageQuotaService.
        if ($canonicalResource === 'storage_gb') {
            return $this->continueWhenAllowed($request, $next, $canonicalResource, $plan->name);
        }

        $tenantId = (string) $tenant->getTenantKey();
        $lock = Cache::lock("plan-limit:{$tenantId}:{$canonicalResource}", self::RESOURCE_LOCK_SECONDS);

        if (! $lock->get()) {
            return ApiResponseService::error(
                'PLAN_LIMIT_CHECK_BUSY',
                'PLAN_LIMIT_CHECK_BUSY',
                ['resource' => $canonicalResource],
                409,
            );
        }

        try {
            return $this->continueWhenAllowed($request, $next, $canonicalResource, $plan->name);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    private function continueWhenAllowed(
        Request $request,
        Closure $next,
        string $resource,
        string $planName,
    ): Response {
        if ($this->checkResourceLimit($resource, $request)) {
            return ApiResponseService::error(
                'PLAN_LIMIT_EXCEEDED',
                "Limite do plano atingido para {$resource}. Faça upgrade para continuar.",
                [
                    'resource' => $resource,
                    'plan' => $planName,
                    'upgrade_url' => '/api/v1/tenant/subscription/upgrade',
                ],
                403
            );
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
