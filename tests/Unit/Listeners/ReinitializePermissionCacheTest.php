<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Listeners\Tenant\ReinitializePermissionCache;
use App\Models\Central\Tenant;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;
use Tests\TestCase;

final class ReinitializePermissionCacheTest extends TestCase
{
    public function test_permission_registrar_and_key_are_reinitialized_at_tenancy_boundaries(): void
    {
        $listener = new ReinitializePermissionCache;
        $tenancy = tenancy();
        $originalTenant = $tenancy->tenant;
        $centralKey = (string) config('permission.cache.central_key');
        $originalRegistrar = app(PermissionRegistrar::class);
        $tenant = new Tenant;
        $tenant->forceFill(['id' => 42]);
        $tenancy->tenant = $tenant;

        try {
            $listener->handle(new TenancyInitialized($tenancy));

            $tenantRegistrar = app(PermissionRegistrar::class);
            $this->assertSame($centralKey.'.tenant.42', config('permission.cache.key'));
            $this->assertNotSame($originalRegistrar, $tenantRegistrar);

            $listener->handle(new TenancyEnded($tenancy));

            $this->assertSame($centralKey, config('permission.cache.key'));
            $this->assertNotSame($tenantRegistrar, app(PermissionRegistrar::class));
        } finally {
            config()->set('permission.cache.key', $centralKey);
            $tenancy->tenant = $originalTenant;
            app()->forgetInstance(PermissionRegistrar::class);
        }
    }
}
