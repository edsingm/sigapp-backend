<?php

namespace App\Services;

use App\Models\Central\Tenant;

class LimitEnforcementService
{
    protected Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Check if the tenant can create a new user.
     */
    public function canCreateUser(): bool
    {
        if ($this->tenant->max_users === -1) {
            return true;
        }

        $currentUsers = app(UsageMetricsService::class)->getUserCount();

        return $currentUsers < $this->tenant->max_users;
    }

    /**
     * Check if the tenant can create a new terreno.
     */
    public function canCreateTerreno(): bool
    {
        if ($this->tenant->max_terrenos === -1) {
            return true;
        }

        $currentTerrenos = app(UsageMetricsService::class)->getTerrenoCount();

        return $currentTerrenos < $this->tenant->max_terrenos;
    }

    /**
     * Check if the tenant can upload a file of the given size (in KB).
     */
    public function canUploadFile(int $sizeInKb): bool
    {
        if ($this->tenant->max_storage_gb === -1) {
            return true;
        }

        $currentUsageBytes = app(UsageMetricsService::class)->getStorageUsedBytes();
        $newSizeBytes = $sizeInKb * 1024;
        $totalBytes = $currentUsageBytes + $newSizeBytes;
        $maxBytes = $this->tenant->max_storage_gb * 1024 * 1024 * 1024;

        return $totalBytes <= $maxBytes;
    }
}
