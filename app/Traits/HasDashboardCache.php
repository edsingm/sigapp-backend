<?php

namespace App\Traits;

use App\Services\Tenant\TenantCacheService;
use Illuminate\Support\Facades\Log;
use Throwable;

trait HasDashboardCache
{
    public static function bootHasDashboardCache(): void
    {
        static::registerModelEvent('saved', static function (self $model): void {
            $model->clearRelatedTenantCache();
        });

        static::registerModelEvent('deleted', static function (self $model): void {
            $model->clearRelatedTenantCache();
        });

        static::registerModelEvent('restored', static function (self $model): void {
            $model->clearRelatedTenantCache();
        });
    }

    /**
     * Get the dashboard cache tag for the current tenant.
     */
    public function getDashboardCacheTag(): string
    {
        return app(TenantCacheService::class)->tag('dashboard');
    }

    /**
     * Clear the dashboard cache for the current tenant.
     */
    public function clearDashboardCache(): void
    {
        try {
            app(TenantCacheService::class)->flushModules('dashboard');
        } catch (Throwable $e) {
            Log::error('Error clearing dashboard cache: '.$e->getMessage());
        }
    }

    /**
     * Clear specific tenant module cache and dashboard cache.
     */
    public function clearTenantCache(string $module): void
    {
        try {
            app(TenantCacheService::class)->flushModules('dashboard', $module);
            Log::debug("Tenant cache cleared for module: {$module}");
        } catch (Throwable $e) {
            Log::error('Error clearing tenant module cache: '.$e->getMessage());
        }
    }

    /**
     * @return list<string>
     */
    protected function tenantCacheModules(): array
    {
        return [];
    }

    protected function clearRelatedTenantCache(): void
    {
        try {
            app(TenantCacheService::class)->flushModules(
                'dashboard',
                ...$this->tenantCacheModules(),
            );
        } catch (Throwable $e) {
            Log::error('Error clearing related tenant caches: '.$e->getMessage());
        }
    }
}
