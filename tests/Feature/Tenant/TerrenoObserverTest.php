<?php

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Jobs\CalculateUsableAreaJob;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TerrenoObserverTest extends TestCase
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
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => \Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_dispatches_job_when_polygon_coords_is_set_on_create(): void
    {
        Bus::fake(CalculateUsableAreaJob::class);

        Terreno::create([
            'nome' => 'Teste',
            'polygon_coords' => [
                ['lat' => -23.5, 'lng' => -46.6],
                ['lat' => -23.5, 'lng' => -46.7],
                ['lat' => -23.6, 'lng' => -46.7],
            ],
            'created_by' => $this->admin->id,
        ]);

        Bus::assertDispatched(CalculateUsableAreaJob::class);
    }

    public function test_does_not_dispatch_job_when_polygon_coords_is_null(): void
    {
        Bus::fake(CalculateUsableAreaJob::class);

        Terreno::create([
            'nome' => 'Teste',
            'created_by' => $this->admin->id,
        ]);

        Bus::assertNotDispatched(CalculateUsableAreaJob::class);
    }

    public function test_dispatches_job_when_polygon_coords_is_updated(): void
    {
        $terreno = Terreno::create([
            'nome' => 'Teste',
            'created_by' => $this->admin->id,
        ]);

        Bus::fake(CalculateUsableAreaJob::class);

        $terreno->update([
            'nome' => 'Teste Atualizado',
            'polygon_coords' => [
                ['lat' => -23.5, 'lng' => -46.6],
                ['lat' => -23.5, 'lng' => -46.7],
                ['lat' => -23.6, 'lng' => -46.7],
            ],
        ]);

        Bus::assertDispatched(CalculateUsableAreaJob::class);
    }
}
