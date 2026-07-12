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
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjetoPlanningApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Projeto $projeto;

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
            'name' => 'Project Planning Admin',
            'email' => 'project-planning-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);

        $terreno = Terreno::create([
            'nome' => 'Terreno do projeto',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
        $this->projeto = Projeto::create([
            'nome' => 'Projeto com planejamento',
            'terreno_id' => $terreno->id,
            'status' => 'em_legalizacao',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_manage_milestones_dependencies_and_risks(): void
    {
        $first = $this->actingAs($this->admin)->postJson(
            "/api/v1/projetos/{$this->projeto->id}/milestones",
            ['name' => 'Aprovar projeto', 'position' => 0],
        )->assertCreated()->json('data.id');

        $second = $this->actingAs($this->admin)->postJson(
            "/api/v1/projetos/{$this->projeto->id}/milestones",
            ['name' => 'Protocolar registro', 'position' => 1],
        )->assertCreated()->json('data.id');

        $this->actingAs($this->admin)->postJson(
            "/api/v1/projetos/{$this->projeto->id}/dependencies",
            ['predecessor_milestone_id' => $first, 'successor_milestone_id' => $second],
        )->assertCreated()->assertJsonPath('data.predecessor_milestone_id', $first);

        $this->actingAs($this->admin)->postJson(
            "/api/v1/projetos/{$this->projeto->id}/dependencies",
            ['predecessor_milestone_id' => $second, 'successor_milestone_id' => $first],
        )->assertUnprocessable()->assertJsonPath('error.code', 'PROJECT_DEPENDENCY_INVALID');

        $this->actingAs($this->admin)->postJson(
            "/api/v1/projetos/{$this->projeto->id}/risks",
            ['title' => 'Atraso cartorial', 'severity' => 'high'],
        )->assertCreated()->assertJsonPath('data.title', 'Atraso cartorial');

        $this->actingAs($this->admin)->postJson(
            "/api/v1/projetos/{$this->projeto->id}/milestones/reorder",
            ['milestone_ids' => [$second, $first]],
        )->assertOk()->assertJsonPath('data.0.id', $second);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/projetos/{$this->projeto->id}/milestones")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Protocolar registro');
    }
}
