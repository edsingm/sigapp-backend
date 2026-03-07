<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait HasDashboardCache
{
    /**
     * Get the dashboard cache tag for the current tenant.
     */
    public function getDashboardCacheTag(): string
    {
        $tenantId = tenant('id') ?? 'central';
        return "tenant:{$tenantId}:dashboard";
    }

    /**
     * Clear the dashboard cache for the current tenant.
     */
    public function clearDashboardCache(): void
    {
        try {
            $tag = $this->getDashboardCacheTag();
            
            // Tags are only supported by redis and memcached drivers
            if (config('cache.default') === 'redis' || config('cache.default') === 'memcached') {
                Cache::tags([$tag])->flush();
            } else {
                // Fallback: clear all cache if tags are not supported (less efficient)
                // Or we could implement a more specific key-based clearing if needed
                // For now, let's just log or do nothing to avoid clearing everything
                Log::info("Dashboard cache clear requested but driver does not support tags: " . config('cache.default'));
            }
        } catch (\Exception $e) {
            Log::error("Error clearing dashboard cache: " . $e->getMessage());
        }
    }

    /**
     * Clear specific tenant module cache and dashboard cache.
     */
    public function clearTenantCache(string $module): void
    {
        // Always clear the general dashboard cache
        $this->clearDashboardCache();

        try {
            $tenantId = tenant('id') ?? 'central';
            $tag = "tenant:{$tenantId}:{$module}";

            // Tags are only supported by redis and memcached drivers
            if (config('cache.default') === 'redis' || config('cache.default') === 'memcached') {
                Cache::tags([$tag])->flush();
                Log::debug("Tenant cache cleared for module: {$module}", ['tag' => $tag]);
            }
        } catch (\Exception $e) {
            Log::error("Error clearing tenant module cache: " . $e->getMessage());
        }
    }
}
