<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Services\Tenant\TenantCacheService;
use Tests\TestCase;

final class TenantCacheServiceTest extends TestCase
{
    public function test_module_invalidation_preserves_unrelated_cache_entries(): void
    {
        $cache = app(TenantCacheService::class);
        $terrainKey = $cache->key('terrenos', 'index', ['page' => 1]);
        $dashboardKey = $cache->key('dashboard', 'cards');
        $documentsKey = $cache->key('documentos', 'index', ['page' => 1]);

        $cache->remember('terrenos', $terrainKey, 300, fn (): string => 'terrain');
        $cache->remember('dashboard', $dashboardKey, 300, fn (): string => 'dashboard');
        $cache->remember('documentos', $documentsKey, 300, fn (): string => 'documents');

        $cache->flushModules('dashboard', 'terrenos');

        $this->assertSame('new-terrain', $cache->remember('terrenos', $terrainKey, 300, fn (): string => 'new-terrain'));
        $this->assertSame('new-dashboard', $cache->remember('dashboard', $dashboardKey, 300, fn (): string => 'new-dashboard'));
        $this->assertSame('documents', $cache->remember('documentos', $documentsKey, 300, fn (): string => 'changed-documents'));
    }

    public function test_remember_coalesces_cache_hits_and_force_refresh_replaces_the_value(): void
    {
        $cache = app(TenantCacheService::class);
        $key = $cache->key('dashboard', 'management', ['limit' => 8]);
        $calls = 0;
        $resolver = function () use (&$calls): array {
            return ['value' => ++$calls];
        };

        $this->assertSame(['value' => 1], $cache->remember('dashboard', $key, 300, $resolver));
        $this->assertSame(['value' => 1], $cache->remember('dashboard', $key, 300, $resolver));
        $this->assertSame(['value' => 2], $cache->remember('dashboard', $key, 300, $resolver, forceRefresh: true));
        $this->assertSame(['value' => 2], $cache->remember('dashboard', $key, 300, $resolver));
        $this->assertSame(2, $calls);
    }

    public function test_cache_key_is_stable_for_equivalent_associative_context(): void
    {
        $cache = app(TenantCacheService::class);

        $first = $cache->key('terrenos', 'filter', [
            'page' => 2,
            'filters' => ['status' => 'lead', 'search' => 'centro'],
        ]);
        $second = $cache->key('terrenos', 'filter', [
            'filters' => ['search' => 'centro', 'status' => 'lead'],
            'page' => 2,
        ]);

        $this->assertSame($first, $second);
    }

    public function test_failed_force_refresh_keeps_the_previous_value_available(): void
    {
        $cache = app(TenantCacheService::class);
        $key = $cache->key('dashboard', 'atomic-refresh');
        $cache->remember('dashboard', $key, 300, fn (): string => 'stable');

        $this->expectException(\RuntimeException::class);

        try {
            $cache->remember(
                'dashboard',
                $key,
                300,
                fn (): never => throw new \RuntimeException('refresh failed'),
                forceRefresh: true,
            );
        } finally {
            $this->assertSame(
                'stable',
                $cache->remember('dashboard', $key, 300, fn (): string => 'unexpected'),
            );
        }
    }
}
