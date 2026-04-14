<?php

namespace App\Services;

use App\Models\Tenant\Documento;
use App\Models\Tenant\Produto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;

class UsageMetricsService
{
    /**
     * Obtém a contagem de usuários no tenant atual.
     */
    public function getUserCount(): int
    {
        if (! tenancy()->initialized) {
            return 0;
        }

        return User::count();
    }

    /**
     * Obtém a contagem de terrenos no tenant atual.
     */
    public function getTerrenoCount(): int
    {
        if (! tenancy()->initialized) {
            return 0;
        }

        return Terreno::count();
    }

    /**
     * Obtém a contagem de produtos no tenant atual.
     */
    public function getProdutoCount(): int
    {
        if (! tenancy()->initialized) {
            return 0;
        }

        return Produto::count();
    }

    /**
     * Obtém o armazenamento usado em bytes.
     */
    public function getStorageUsedBytes(): int
    {
        if (! tenancy()->initialized) {
            return 0;
        }

        return (int) Documento::query()->sum('tamanho');
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
        $plan = $tenant?->plan;

        return [
            'users' => [
                'current' => $this->getUserCount(),
                'limit' => $plan?->getLimit('users') ?? 0,
                'unlimited' => $plan?->hasUnlimitedLimit('users') ?? false,
            ],
            'terrenos' => [
                'current' => $this->getTerrenoCount(),
                'limit' => $plan?->getLimit('terrenos') ?? 0,
                'unlimited' => $plan?->hasUnlimitedLimit('terrenos') ?? false,
            ],
            'products' => [
                'current' => $this->getProdutoCount(),
                'limit' => $plan?->getLimit('products') ?? 0,
                'unlimited' => $plan?->hasUnlimitedLimit('products') ?? false,
            ],
            'storage' => [
                'used_gb' => $this->getStorageUsed(),
                'limit_gb' => $plan?->getLimit('storage_gb') ?? 0,
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
