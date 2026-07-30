<?php

namespace App\Services;

use App\Repositories\Contracts\UsageMetricsRepositoryInterface;

class UsageMetricsService
{
    public function __construct(
        private readonly PlanMatrixService $planMatrix,
        private readonly UsageMetricsRepositoryInterface $repository,
    ) {}

    /**
     * Obtém a contagem de usuários no tenant atual.
     */
    public function getUserCount(): int
    {
        if (! tenancy()->initialized) {
            return 0;
        }

        return $this->repository->userCount();
    }

    /**
     * Obtém a contagem de terrenos no tenant atual.
     */
    public function getTerrenoCount(): int
    {
        if (! tenancy()->initialized) {
            return 0;
        }

        return $this->repository->terrenoCount();
    }

    /**
     * Obtém a contagem de produtos no tenant atual.
     */
    public function getProdutoCount(): int
    {
        if (! tenancy()->initialized) {
            return 0;
        }

        return $this->repository->produtoCount();
    }

    /**
     * Obtém o armazenamento usado em bytes.
     */
    public function getStorageUsedBytes(): int
    {
        if (! tenancy()->initialized) {
            return 0;
        }

        return $this->repository->storageUsedBytes();
    }

    /**
     * Obtém o armazenamento usado em GB.
     */
    public function getStorageUsed(): float
    {
        return round($this->getStorageUsedBytes() / (1024 * 1024 * 1024), 2);
    }

    /**
     * Obtém todas as métricas de uso para o tenant atual.
     */
    public function getMetrics(): array
    {
        $tenant = tenancy()->tenant;
        $hasPlan = $tenant && is_int($tenant->getAttribute('plan_id'));

        return [
            'users' => [
                'current' => $this->getUserCount(),
                'limit' => $hasPlan ? $this->planMatrix->getLimitForTenant($tenant, 'users') : 0,
                'unlimited' => $hasPlan && $this->planMatrix->isUnlimitedLimitForTenant($tenant, 'users'),
            ],
            'terrenos' => [
                'current' => $this->getTerrenoCount(),
                'limit' => $hasPlan ? $this->planMatrix->getLimitForTenant($tenant, 'terrenos') : 0,
                'unlimited' => $hasPlan && $this->planMatrix->isUnlimitedLimitForTenant($tenant, 'terrenos'),
            ],
            'products' => [
                'current' => $this->getProdutoCount(),
                'limit' => $hasPlan ? $this->planMatrix->getLimitForTenant($tenant, 'products') : 0,
                'unlimited' => $hasPlan && $this->planMatrix->isUnlimitedLimitForTenant($tenant, 'products'),
            ],
            'storage' => [
                'used_gb' => $this->getStorageUsed(),
                'limit_gb' => $hasPlan ? $this->planMatrix->getLimitForTenant($tenant, 'storage_gb') : 0,
            ],
        ];
    }

    /**
     * Obtém as porcentagens de uso.
     */
    public function getUsagePercentages(): array
    {
        $metrics = $this->getMetrics();

        return [
            'users' => $metrics['users']['unlimited']
                ? 0
                : ($metrics['users']['limit'] > 0
                    ? round(($metrics['users']['current'] / $metrics['users']['limit']) * 100, 1)
                    : 100),
            'terrenos' => $metrics['terrenos']['unlimited']
                ? 0
                : ($metrics['terrenos']['limit'] > 0
                    ? round(($metrics['terrenos']['current'] / $metrics['terrenos']['limit']) * 100, 1)
                    : 100),
            'products' => $metrics['products']['unlimited']
                ? 0
                : ($metrics['products']['limit'] > 0
                    ? round(($metrics['products']['current'] / $metrics['products']['limit']) * 100, 1)
                    : 100),
            'storage' => $metrics['storage']['limit_gb'] > 0
                ? round(($metrics['storage']['used_gb'] / $metrics['storage']['limit_gb']) * 100, 1)
                : 100,
        ];
    }

    /**
     * Verifica se algum limite está se aproximando (80% ou mais).
     */
    public function isApproachingLimits(): bool
    {
        $percentages = $this->getUsagePercentages();

        return $percentages['users'] >= 80
            || $percentages['terrenos'] >= 80
            || $percentages['products'] >= 80
            || $percentages['storage'] >= 80;
    }
}
