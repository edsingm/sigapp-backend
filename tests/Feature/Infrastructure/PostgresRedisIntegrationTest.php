<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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

                Cache::forget('infrastructure:shared-key');
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

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => Str::headline($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
            'admin_name' => 'CI Admin',
            'admin_email' => "{$slug}@example.test",
            'admin_password' => 'not-used',
        ]);
    }
}
