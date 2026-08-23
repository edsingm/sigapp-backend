<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Jobs\CreateFullTenantJob;
use App\Jobs\QueueHeartbeatJob;
use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;
use App\Services\Tenant\TenantCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class PostgresRedisIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql' || config('cache.default') !== 'redis') {
            $this->markTestSkipped('Este teste requer PostgreSQL e Redis.');
        }
    }

    public function test_tenant_schemas_and_cache_are_isolated(): void
    {
        $suffix = strtolower(Str::random(8));
        $tenants = [
            $this->makeTenant("ci-alpha-{$suffix}"),
            $this->makeTenant("ci-beta-{$suffix}"),
        ];

        try {
            foreach ($tenants as $index => $tenant) {
                $manager = $tenant->database()->manager();
                $manager->createDatabase($tenant);

                tenancy()->initialize($tenant);

                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => database_path('migrations/tenant'),
                    '--realpath' => true,
                    '--force' => true,
                ]);

                DB::connection('tenant')->table('users')->insert([
                    'name' => "Tenant {$index}",
                    'email' => "tenant-{$index}@example.test",
                    'password' => 'not-used',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                Cache::put('infrastructure:shared-key', "tenant-{$index}", 60);

                $tenantCache = app(TenantCacheService::class);
                $moduleKey = $tenantCache->key('infrastructure-module', 'shared');
                $unrelatedKey = $tenantCache->key('infrastructure-unrelated', 'shared');
                $tenantCache->remember('infrastructure-module', $moduleKey, 60, fn (): string => "module-{$index}");
                $tenantCache->remember('infrastructure-unrelated', $unrelatedKey, 60, fn (): string => "unrelated-{$index}");

                if ($index === 0) {
                    $tenantCache->flushModules('infrastructure-module');
                    $this->assertSame(
                        'module-0-refreshed',
                        $tenantCache->remember('infrastructure-module', $moduleKey, 60, fn (): string => 'module-0-refreshed'),
                    );
                    $this->assertSame(
                        'unrelated-0',
                        $tenantCache->remember('infrastructure-unrelated', $unrelatedKey, 60, fn (): string => 'unexpected'),
                    );
                }

                $this->assertSame(
                    $tenant->database()->getName(),
                    DB::connection('tenant')->selectOne('select current_schema() as schema')->schema,
                );

                tenancy()->end();
            }

            foreach ($tenants as $index => $tenant) {
                tenancy()->initialize($tenant);

                $this->assertSame(
                    ["tenant-{$index}@example.test"],
                    DB::connection('tenant')->table('users')->pluck('email')->all(),
                );
                $this->assertSame("tenant-{$index}", Cache::get('infrastructure:shared-key'));

                $tenantCache = app(TenantCacheService::class);
                $moduleKey = $tenantCache->key('infrastructure-module', 'shared');
                $unrelatedKey = $tenantCache->key('infrastructure-unrelated', 'shared');
                $this->assertSame(
                    $index === 0 ? 'module-0-refreshed' : 'module-1',
                    $tenantCache->remember('infrastructure-module', $moduleKey, 60, fn (): string => 'unexpected'),
                );
                $this->assertSame(
                    "unrelated-{$index}",
                    $tenantCache->remember('infrastructure-unrelated', $unrelatedKey, 60, fn (): string => 'unexpected'),
                );

                Cache::forget('infrastructure:shared-key');
                $tenantCache->flushModules('infrastructure-module', 'infrastructure-unrelated');
                tenancy()->end();
            }
        } finally {
            tenancy()->end();

            foreach ($tenants as $tenant) {
                $manager = $tenant->database()->manager();
                if ($manager->databaseExists((string) $tenant->database()->getName())) {
                    $manager->deleteDatabase($tenant);
                }
            }
        }
    }

    public function test_provisioning_creates_key_and_only_activates_after_postconditions(): void
    {
        Notification::fake();
        $slug = 'ci-provision-'.strtolower(Str::random(8));
        $tenant = Tenant::query()->create([
            'name' => Str::headline($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_PENDING,
            'admin_name' => 'Provision Admin',
            'admin_email' => "{$slug}@example.test",
            'admin_password' => 'password123',
            'database_created' => false,
        ]);
        $manager = $tenant->database()->manager();
        $databaseName = (string) $tenant->database()->getName();

        try {
            (new CreateFullTenantJob($tenant))->handle();

            $tenant->refresh();
            $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->getAttribute('status'));
            $this->assertTrue((bool) $tenant->getAttribute('database_created'));
            $this->assertNotNull($tenant->getAttribute('setup_completed_at'));
            $this->assertStringStartsWith('enc:v1:', (string) $tenant->getAttribute('encryption_key'));
            $this->assertTrue(TenantUserDirectory::query()
                ->where('tenant_id', (string) $tenant->getKey())
                ->where('email_normalized', "{$slug}@example.test")
                ->where('active', true)
                ->exists());

            tenancy()->initialize($tenant);
            $this->assertTrue(DB::connection('tenant')->table('users')->exists());
            $this->assertTrue(DB::connection('tenant')->table('roles')->exists());
        } finally {
            tenancy()->end();

            if ($manager->databaseExists($databaseName)) {
                $manager->deleteDatabase($tenant);
            }
        }
    }

    public function test_redis_provides_distributed_locks_and_queue_transport(): void
    {
        $suffix = strtolower(Str::random(12));
        $lockKey = "infrastructure:lock:{$suffix}";
        $queueName = "infrastructure-{$suffix}";
        $firstLock = Cache::lock($lockKey, 10);
        $secondLock = Cache::lock($lockKey, 10);
        $queue = Queue::connection('redis');

        try {
            $this->assertTrue($firstLock->get());
            $this->assertFalse($secondLock->get());

            $queue->pushRaw(json_encode([
                'id' => $suffix,
                'attempts' => 0,
                'probe' => true,
            ], JSON_THROW_ON_ERROR), $queueName);
            $this->assertSame(1, $queue->size($queueName));

            $job = $queue->pop($queueName);
            $this->assertNotNull($job);
            $job->delete();
            $this->assertSame(0, $queue->size($queueName));
        } finally {
            $firstLock->release();

            if (method_exists($queue, 'clear')) {
                $queue->clear($queueName);
            }
        }

        $this->assertTrue($secondLock->get());
        $secondLock->release();
    }

    public function test_a_real_laravel_job_is_consumed_by_a_redis_worker(): void
    {
        $suffix = strtolower(Str::random(12));
        $queueName = "infrastructure-job-{$suffix}";
        $heartbeatKey = "operations:queue:{$queueName}";
        Cache::forget($heartbeatKey);

        QueueHeartbeatJob::dispatch($queueName, now()->subSeconds(2)->timestamp)
            ->onConnection('redis')
            ->onQueue($queueName);

        $this->assertSame(1, Queue::connection('redis')->size($queueName));
        $exitCode = Artisan::call('queue:work', [
            'connection' => 'redis',
            '--queue' => $queueName,
            '--once' => true,
            '--stop-when-empty' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertIsArray(Cache::get($heartbeatKey));
        $this->assertSame(0, Queue::connection('redis')->size($queueName));
        Cache::forget($heartbeatKey);
    }

    public function test_permission_cache_is_reinitialized_between_tenants_in_the_same_worker(): void
    {
        $suffix = strtolower(Str::random(8));
        $tenants = [
            $this->makeTenant("ci-permission-alpha-{$suffix}"),
            $this->makeTenant("ci-permission-beta-{$suffix}"),
        ];

        try {
            foreach ($tenants as $index => $tenant) {
                $manager = $tenant->database()->manager();
                $manager->createDatabase($tenant);
                tenancy()->initialize($tenant);

                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => database_path('migrations/tenant'),
                    '--realpath' => true,
                    '--force' => true,
                ]);

                DB::connection('tenant')->table('permissions')->insert([
                    'name' => $index === 0 ? 'alpha-only' : 'beta-only',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                tenancy()->end();
            }

            foreach ($tenants as $index => $tenant) {
                tenancy()->initialize($tenant);

                $this->assertSame(
                    'spatie.permission.cache.tenant.'.$tenant->getTenantKey(),
                    config('permission.cache.key'),
                );
                $this->assertSame(
                    [$index === 0 ? 'alpha-only' : 'beta-only'],
                    app(PermissionRegistrar::class)->getPermissions()->pluck('name')->all(),
                );

                app(PermissionRegistrar::class)->forgetCachedPermissions();
                tenancy()->end();

                $this->assertSame(
                    config('permission.cache.central_key'),
                    config('permission.cache.key'),
                );
            }
        } finally {
            tenancy()->end();

            foreach ($tenants as $tenant) {
                $manager = $tenant->database()->manager();
                if ($manager->databaseExists((string) $tenant->database()->getName())) {
                    $manager->deleteDatabase($tenant);
                }
            }
        }
    }

    private function makeTenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => Str::headline($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
            'admin_name' => 'CI Admin',
            'admin_email' => "{$slug}@example.test",
            'admin_password' => 'not-used',
        ]);

        $tenant->ensureEncryptionKey();

        return $tenant;
    }
}
