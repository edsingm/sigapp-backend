<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Central\Tenant;
use App\Services\Privacy\TenantLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ResetAllTenantsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_testing_reset_removes_tenant_schema_storage_and_unconstrained_central_records(): void
    {
        Storage::fake('s3');

        $tenant = $this->makeTenant('reset-testing');
        $tenantId = (string) $tenant->getKey();
        $tenantArtifact = "tenants/{$tenantId}/documents/example.pdf";
        $centralArtifact = 'hiperdados-imports/'.Str::uuid().'.json';

        Storage::disk('s3')->put($tenantArtifact, 'tenant');
        Storage::disk('s3')->put($centralArtifact, 'central');

        DB::table('subscriptions')->insert([
            'tenant_id' => $tenantId,
            'type' => 'default',
            'stripe_id' => 'sub_reset_testing',
            'stripe_status' => 'active',
            'stripe_price' => 'price_testing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenant_user_directories')->insert([
            'tenant_id' => $tenantId,
            'tenant_user_id' => 'tenant-user-1',
            'email_normalized' => 'reset@example.com',
            'user_name' => 'Reset',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('hiperdados_imports')->insert([
            'uuid' => (string) Str::uuid(),
            'status' => 'ready',
            'portal_username' => 'reset',
            'tenant_id' => $tenantId,
            'total_count' => 0,
            'processed_count' => 0,
            'failed_count' => 0,
            'imported_count' => 0,
            'storage_disk' => 's3',
            'storage_path' => $centralArtifact,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->pendingArtisan('tenants:reset-all')
            ->expectsConfirmation(
                'Apagar todos os tenants, schemas, arquivos e vínculos centrais deste ambiente?',
                'yes',
            )
            ->expectsOutputToContain('Reset concluído: 1 removido(s), 0 falha(s), 1 processado(s).')
            ->assertSuccessful();

        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('tenant_user_directories', 0);
        $this->assertDatabaseCount('hiperdados_imports', 0);
        $this->assertFalse(Storage::disk('s3')->exists($tenantArtifact));
        $this->assertFalse(Storage::disk('s3')->exists($centralArtifact));
    }

    public function test_reset_is_blocked_in_production(): void
    {
        Storage::fake('s3');
        $tenant = $this->makeTenant('blocked-production');
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->pendingArtisan('tenants:reset-all')
            ->expectsOutputToContain('Reset bloqueado no ambiente [production].')
            ->assertFailed();

        $this->assertDatabaseHas('tenants', ['id' => (string) $tenant->getKey()]);
    }

    public function test_staging_requires_force_flag(): void
    {
        Storage::fake('s3');
        $tenant = $this->makeTenant('protected-staging');
        $this->app->detectEnvironment(static fn (): string => 'staging');

        $this->pendingArtisan('tenants:reset-all')
            ->expectsOutputToContain('Em staging, informe --force-staging')
            ->assertFailed();

        $this->assertDatabaseHas('tenants', ['id' => (string) $tenant->getKey()]);
    }

    public function test_staging_refuses_reset_when_subscription_can_still_be_active(): void
    {
        Storage::fake('s3');
        $tenant = $this->makeTenant('active-subscription-staging');
        $tenantId = (string) $tenant->getKey();

        DB::table('subscriptions')->insert([
            'tenant_id' => $tenantId,
            'type' => 'default',
            'stripe_id' => 'sub_active_staging',
            'stripe_status' => 'active',
            'stripe_price' => 'price_staging',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app->detectEnvironment(static fn (): string => 'staging');

        $this->pendingArtisan('tenants:reset-all', ['--force-staging' => true])
            ->expectsOutputToContain('staging possui assinaturas Stripe potencialmente ativas')
            ->assertFailed();

        $this->assertDatabaseHas('tenants', ['id' => $tenantId]);
    }

    public function test_staging_requires_exact_confirmation_phrase(): void
    {
        Storage::fake('s3');
        $tenant = $this->makeTenant('phrase-staging');
        $this->app->detectEnvironment(static fn (): string => 'staging');

        $this->pendingArtisan('tenants:reset-all', ['--force-staging' => true])
            ->expectsQuestion(
                'Digite RESET STAGING para apagar todos os tenants deste ambiente',
                'reset staging',
            )
            ->expectsOutputToContain('Reset de staging cancelado.')
            ->assertSuccessful();

        $this->assertDatabaseHas('tenants', ['id' => (string) $tenant->getKey()]);
    }

    public function test_staging_reset_runs_after_force_and_exact_confirmation(): void
    {
        Storage::fake('s3');
        $this->makeTenant('confirmed-staging');
        $this->app->detectEnvironment(static fn (): string => 'staging');

        $this->pendingArtisan('tenants:reset-all', ['--force-staging' => true])
            ->expectsQuestion(
                'Digite RESET STAGING para apagar todos os tenants deste ambiente',
                'RESET STAGING',
            )
            ->expectsOutputToContain('Reset concluído: 1 removido(s), 0 falha(s), 1 processado(s).')
            ->assertSuccessful();

        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_reset_continues_after_individual_failure_and_returns_failure(): void
    {
        Storage::fake('s3');
        $failing = $this->makeTenant('a-failing-reset');
        $removable = $this->makeTenant('z-removable-reset');

        $lifecycle = Mockery::mock(TenantLifecycleService::class);
        $lifecycle->shouldReceive('wipe')
            ->twice()
            ->andReturnUsing(static function (Tenant $tenant): Tenant {
                if ($tenant->getAttribute('slug') === 'a-failing-reset') {
                    throw new RuntimeException('storage indisponível');
                }

                return $tenant;
            });
        $this->app->instance(TenantLifecycleService::class, $lifecycle);

        $this->pendingArtisan('tenants:reset-all')
            ->expectsConfirmation(
                'Apagar todos os tenants, schemas, arquivos e vínculos centrais deste ambiente?',
                'yes',
            )
            ->expectsOutputToContain('não foi removido: storage indisponível')
            ->expectsOutputToContain('Reset concluído: 1 removido(s), 1 falha(s), 2 processado(s).')
            ->assertFailed();

        $this->assertDatabaseHas('tenants', ['id' => (string) $failing->getKey()]);
        $this->assertDatabaseMissing('tenants', ['id' => (string) $removable->getKey()]);
    }

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => Str::headline($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_PENDING,
            'database_created' => false,
        ]);
    }

    /** @param array<string, mixed> $parameters */
    private function pendingArtisan(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);
        $this->assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }
}
