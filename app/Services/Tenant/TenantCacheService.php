<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as CacheRepositoryContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use LogicException;

final class TenantCacheService
{
    private const FILL_LOCK_SECONDS = 60;

    private const FILL_LOCK_WAIT_SECONDS = 5;

    /**
     * @param  array<string, mixed>  $context
     */
    public function key(string $module, string $scope, array $context = []): string
    {
        $key = implode(':', [
            'tenant',
            $this->tenantId(),
            $module,
            $scope,
        ]);

        if ($context === []) {
            return $key;
        }

        $context = $this->normalize($context);
        $encoded = json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $key.':'.hash('sha256', $encoded);
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $resolver
     * @return TValue
     */
    public function remember(
        string $module,
        string $key,
        DateTimeInterface|DateInterval|int $ttl,
        Closure $resolver,
        bool $forceRefresh = false,
        int $lockSeconds = self::FILL_LOCK_SECONDS,
        int $lockWaitSeconds = self::FILL_LOCK_WAIT_SECONDS,
    ): mixed {
        $store = $this->tagged($module);

        if (! $forceRefresh) {
            $cached = $store->get($key);

            if ($cached !== null) {
                return $cached;
            }
        }

        $lockKey = implode(':', [
            'tenant',
            $this->tenantId(),
            'cache-fill',
            hash('sha256', $key),
        ]);

        try {
            return Cache::lock($lockKey, $lockSeconds)
                ->block($lockWaitSeconds, function () use ($forceRefresh, $key, $resolver, $store, $ttl): mixed {
                    if (! $forceRefresh) {
                        return $store->remember($key, $ttl, $resolver);
                    }

                    $value = $resolver();
                    $store->put($key, $value, $ttl);

                    return $value;
                });
        } catch (LockTimeoutException) {
            $cached = $store->get($key);

            if ($cached !== null) {
                return $cached;
            }

            Log::warning('Tenant cache fill lock timed out; computing without the lock.', [
                'tenant_id' => $this->tenantId(),
                'module' => $module,
                'key_hash' => hash('sha256', $key),
            ]);

            $value = $resolver();
            $store->put($key, $value, $ttl);

            return $value;
        }
    }

    public function flushModules(string ...$modules): void
    {
        $tags = array_values(array_unique(array_map(
            fn (string $module): string => $this->tag($module),
            $modules,
        )));

        if ($tags === []) {
            return;
        }

        /*
         * Deliberately bypass Stancl's CacheManager here. Its tags() method
         * appends the tenant base tag, and flushing that tag invalidates every
         * cached module for the tenant instead of only the requested modules.
         */
        $repository = Cache::store();

        if (! $repository instanceof CacheRepository) {
            throw new LogicException('The configured cache repository does not support tagged invalidation.');
        }

        $repository->tags($tags)->flush();
    }

    public function tag(string $module): string
    {
        return 'tenant:'.$this->tenantId().':'.$module;
    }

    private function tagged(string $module): CacheRepositoryContract
    {
        return Cache::tags([$this->tag($module)]);
    }

    private function tenantId(): string
    {
        return (string) (tenant('id') ?? 'central');
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function normalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalize($item);
            }
        }

        return $value;
    }
}
