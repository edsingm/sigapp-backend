<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\PremissasViabilidade;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PremissasViabilidadeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            InitializeTenancyFlexible::class,
            AddTenantContextToLogs::class,
            ApiRequestLogger::class,
            CheckSubscriptionStatus::class,
            EnsureTenantContext::class,
            EnsureTenantUser::class,
            EnsureTenantAdmin::class,
            CheckFeature::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->adminUser = User::create([
            'name' => 'Admin Test',
            'email' => 'premissas-admin@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->adminUser->assignRole('admin');
    }

    public function test_it_filters_premissas_by_ativo_query_param(): void
    {
        $ativa = PremissasViabilidade::factory()->ativa()->createOne([
            'nome' => 'Modelo ativo',
            'versao' => 1,
        ]);

        PremissasViabilidade::factory()->inativa()->createOne([
            'nome' => 'Modelo inativo',
            'versao' => 2,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/premissas-viabilidade?ativo=true&per_page=100');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ativa->id)
            ->assertJsonPath('data.0.ativo', true);
    }
}
