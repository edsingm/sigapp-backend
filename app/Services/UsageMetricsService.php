<?php

namespace App\Services;

use App\Models\Tenant\Documento;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;

class UsageMetricsService
{
    /**
     * Get the count of users in the current tenant.
     */
    public function getUserCount(): int
    {
        if (!tenancy()->initialized) {
            return 0;
        }

        return User::count();
    }

    /**
     * Get the count of terrenos in the current tenant.
     */
    public function getTerrenoCount(): int
    {
        if (!tenancy()->initialized) {
            return 0;
        }

        return Terreno::count();
    }

    /**
     * Get the storage used in bytes.
     */
    public function getStorageUsedBytes(): int
    {
        if (!tenancy()->initialized) {
            return 0;
        }

        return (int) Documento::query()->sum('tamanho');
    }

    /**
     * Get the storage used in GB.
     */
    public function getStorageUsed(): float
    {
        return round($this->getStorageUsedBytes() / (1024 * 1024 * 1024), 2);
    }

    /**
     * Get all usage metrics for the current tenant.
     */
    public function getMetrics(): array
    {
        $tenant = tenancy()->tenant;
        $plan = $tenant?->plan;

        return [
            'users' => [
                'current' => $this->getUserCount(),
                'limit' => $plan?->max_users ?? 0,
                'unlimited' => $plan?->hasUnlimitedUsers() ?? false,
            ],
            'terrenos' => [
                'current' => $this->getTerrenoCount(),
                'limit' => $plan?->max_terrenos ?? 0,
                'unlimited' => $plan?->hasUnlimitedTerrenos() ?? false,
            ],
            'storage' => [
                'used_gb' => $this->getStorageUsed(),
                'limit_gb' => $plan?->max_storage_gb ?? 0,
            ],
        ];
    }

    /**
     * Get usage percentages.
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
            'storage' => $metrics['storage']['limit_gb'] > 0
                ? round(($metrics['storage']['used_gb'] / $metrics['storage']['limit_gb']) * 100, 1)
                : 100,
        ];
    }

    /**
     * Check if any limit is approaching (80% or more).
     */
    public function isApproachingLimits(): bool
    {
        $percentages = $this->getUsagePercentages();

        return $percentages['users'] >= 80
            || $percentages['terrenos'] >= 80
            || $percentages['storage'] >= 80;
    }
}
