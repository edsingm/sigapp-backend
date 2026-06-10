<?php

namespace Tests\Feature\Tenant;

use App\Enums\WorkflowStatus;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TerrenoWorkflowApiTest extends TestCase
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
            EnsureTenantContext::class,
            EnsureTenantUser::class,
            EnsureTenantAdmin::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-workflow-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_view_workflow_overview_and_transition_terreno(): void
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno Workflow',
            'created_by' => $this->admin->id,
            'workflow_stage' => WorkflowStatus::EM_ANALISE->stage(),
            'workflow_status_code' => WorkflowStatus::EM_ANALISE->value,
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/terrenos/{$terreno->id}/workflow")
            ->assertOk()
            ->assertJsonPath('data.current_status', WorkflowStatus::EM_ANALISE->value)
            ->assertJsonPath('data.current_stage', WorkflowStatus::EM_ANALISE->stage());

        $this->actingAs($this->admin)
            ->postJson("/api/v1/terrenos/{$terreno->id}/workflow", [
                'target_status' => WorkflowStatus::DESCARTADO->value,
                'reason_code' => 'manual_review',
                'reason_notes' => 'Terreno descartado após análise inicial.',
            ])
            ->assertOk()
            ->assertJsonPath('data.workflow_status_code', WorkflowStatus::DESCARTADO->value)
            ->assertJsonPath('data.workflow_stage', WorkflowStatus::DESCARTADO->stage());

        $this->assertDatabaseHas('status_histories', [
            'terreno_id' => $terreno->id,
            'new_status_code' => WorkflowStatus::DESCARTADO->value,
            'changed_by' => $this->admin->id,
        ]);
    }

    public function test_transition_returns_validation_error_when_prerequisites_are_missing(): void
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno Sem Prerequisitos',
            'created_by' => $this->admin->id,
            'workflow_stage' => WorkflowStatus::EM_ANALISE->stage(),
            'workflow_status_code' => WorkflowStatus::EM_ANALISE->value,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/terrenos/{$terreno->id}/workflow", [
                'target_status' => WorkflowStatus::AGUARDANDO_VIABILIDADE->value,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.target_status.0',
                'Cadastre ao menos um produto no terreno antes de seguir para viabilidade.'
            );
    }

    public function test_transition_returns_unprocessable_when_workflow_transition_is_invalid(): void
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno Com Transicao Invalida',
            'created_by' => $this->admin->id,
            'workflow_stage' => WorkflowStatus::EM_ANALISE->stage(),
            'workflow_status_code' => WorkflowStatus::EM_ANALISE->value,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/terrenos/{$terreno->id}/workflow", [
                'target_status' => WorkflowStatus::LEGALIZANDO->value,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.target_status.0',
                sprintf(
                    'Transição inválida de %s para %s.',
                    WorkflowStatus::EM_ANALISE->value,
                    WorkflowStatus::LEGALIZANDO->value,
                )
            );
    }

    public function test_admin_can_update_qualification_data_and_mark_it_completed(): void
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno Qualificacao',
            'created_by' => $this->admin->id,
            'workflow_stage' => WorkflowStatus::EM_ANALISE->stage(),
            'workflow_status_code' => WorkflowStatus::EM_ANALISE->value,
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/terrenos/{$terreno->id}/qualificacao", [
                'urbanistic_preliminary' => ['zoning' => 'ZR-1'],
                'commercial' => ['price' => 1000000],
                'mark_as_completed' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.qualification_data.urbanistic_preliminary.zoning', 'ZR-1')
            ->assertJsonPath('data.qualification_data.commercial.price', 1000000);

        $terreno->refresh();

        $this->assertSame('ZR-1', $terreno->qualification_data['urbanistic_preliminary']['zoning'] ?? null);
        $this->assertSame($this->admin->id, $terreno->qualification_completed_by);
        $this->assertNotNull($terreno->qualification_completed_at);
    }

    public function test_workflow_requests_require_authentication_and_valid_payloads(): void
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno Protegido',
            'created_by' => $this->admin->id,
            'workflow_stage' => WorkflowStatus::EM_ANALISE->stage(),
            'workflow_status_code' => WorkflowStatus::EM_ANALISE->value,
        ]);

        $this->getJson("/api/v1/terrenos/{$terreno->id}/workflow")
            ->assertUnauthorized();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/terrenos/{$terreno->id}/workflow", [
                'target_status' => 'status_invalido',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_status']);
    }
}
