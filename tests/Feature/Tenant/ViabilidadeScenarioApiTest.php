<?php

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Http\Middleware\PermissionGate;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ViabilidadeScenarioApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Viabilidade $viabilidade;

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
            CheckFeature::class,
            PermissionGate::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Scenario Admin',
            'email' => 'scenario-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);
        $this->viabilidade = Viabilidade::query()->create([
            ...Viabilidade::factory()->raw(),
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_create_and_update_a_scenario(): void
    {
        $create = $this->actingAs($this->admin)->postJson(
            "/api/v1/viabilidades/{$this->viabilidade->id}/scenarios",
            [
                'name' => 'Custo otimista',
                'scenario_type' => 'optimistic',
                'premises' => ['compra_terreno' => 900000],
            ],
        );

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Custo otimista')
            ->assertJsonPath('data.premises.compra_terreno', 900000);

        $scenarioId = $create->json('data.id');

        $this->actingAs($this->admin)
            ->putJson("/api/v1/viabilidades/{$this->viabilidade->id}/scenarios/{$scenarioId}", [
                'name' => 'Custo otimista revisado',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Custo otimista revisado');

        $this->actingAs($this->admin)
            ->getJson("/api/v1/viabilidades/{$this->viabilidade->id}/scenarios")
            ->assertOk()
            ->assertJsonPath('data.0.id', $scenarioId);
    }

    public function test_scenario_rejects_premises_outside_the_viability_contract(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/viabilidades/{$this->viabilidade->id}/scenarios", [
                'name' => 'Cenário inválido',
                'premises' => ['unknown_field' => 10],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SCENARIO_INVALID');
    }
}
