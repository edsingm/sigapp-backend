<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;

final class ReinitializePermissionCache
{
    public function handle(TenancyInitialized|TenancyEnded $event): void
    {
        $centralKey = (string) config('permission.cache.central_key', 'spatie.permission.cache');
        $tenant = $event instanceof TenancyInitialized ? $event->tenancy->tenant : null;
        $tenantKey = $tenant instanceof Tenant ? $tenant->getTenantKey() : null;

        config()->set(
            'permission.cache.key',
            $tenantKey === null
                ? $centralKey
                : $centralKey.'.tenant.'.(string) $tenantKey,
        );

        /*
         * PermissionRegistrar captures its CacheManager and cache repository in
         * the constructor. Re-resolving it after the tenancy bootstrap/revert
         * prevents both the in-memory collection and the persistent cache key
         * from leaking across tenant contexts in a long-running worker.
         */
        app()->forgetInstance(PermissionRegistrar::class);
    }
}
