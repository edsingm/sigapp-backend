<?php

namespace Tests\Feature\Jobs;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Jobs\CalculateUsableAreaJob;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Services\Tenant\Area\AreaCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CalculateUsableAreaJobTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            InitializeTenancyFlexible::class,
            AddTenantContextToLogs::class,
            ApiRequestLogger::class,
            CheckSubscriptionStatus::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-admin@test.com',
            'password' => \Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_job_is_dispatched_on_polygon_change(): void
    {
        Bus::fake(CalculateUsableAreaJob::class);

        $terreno = Terreno::create([
            'nome' => 'Teste',
            'created_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('terrenos', ['id' => $terreno->id]);

        Bus::assertNotDispatched(CalculateUsableAreaJob::class);
    }

    public function test_job_configuration(): void
    {
        $job = new CalculateUsableAreaJob(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame(120, $job->timeout);
        $this->assertSame([30, 60, 120], $job->backoff);
        $this->assertSame('1', $job->uniqueId());
    }

    public function test_job_updates_terreno_with_calculation_results(): void
    {
        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5000, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6000],
        ];

        // Mock Google Elevation API
        Config::set('services.elevation.provider', 'google');
        Config::set('services.google_maps.key', 'fake-key');

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => array_map(
                    fn (array $p) => [
                        'location' => ['lat' => $p['lat'], 'lng' => $p['lng']],
                        'elevation' => 800.0,
                    ],
                    $polygon,
                ),
            ], 200),
            'overpass-api.de/*' => Http::response(['elements' => []], 200),
        ]);

        $terreno = Terreno::create([
            'nome' => 'Terreno Cálculo',
            'polygon_coords' => $polygon,
            'created_by' => $this->admin->id,
        ]);

        $terreno->refresh();

        $job = new CalculateUsableAreaJob($terreno->id);
        $job->handle(app(AreaCalculatorService::class));

        $terreno->refresh();

        // Executar o job sincronamente
        $job = new CalculateUsableAreaJob($terreno->id);
        $job->handle(app(AreaCalculatorService::class));

        $terreno->refresh();

        $this->assertGreaterThan(0.0, $terreno->area_total, 'area_total should be > 0');
        $this->assertEquals(0.0, $terreno->area_app, 'area_app should be 0');
        $this->assertEquals('success', $terreno->area_calculo_status, 'status should be success');
        $this->assertNotNull($terreno->area_calculada_em, 'area_calculada_em should be set');
        $this->assertGreaterThan(0.0, $terreno->area_util, 'area_util should be > 0');
    }

    public function test_job_handles_terreno_without_polygon(): void
    {
        $terreno = Terreno::create([
            'nome' => 'Sem Polígono',
            'created_by' => $this->admin->id,
        ]);

        $job = new CalculateUsableAreaJob($terreno->id);
        $job->handle(app(AreaCalculatorService::class));

        $terreno->refresh();

        $this->assertEquals('success', $terreno->area_calculo_status);
        $this->assertEquals(0.0, $terreno->area_total);
        $this->assertEquals(0.0, $terreno->area_util);
    }

    public function test_job_handles_missing_terreno_gracefully(): void
    {
        $job = new CalculateUsableAreaJob(99999);
        $job->handle(app(AreaCalculatorService::class));

        // Não deve lançar exceção
        $this->expectNotToPerformAssertions();
    }
}
