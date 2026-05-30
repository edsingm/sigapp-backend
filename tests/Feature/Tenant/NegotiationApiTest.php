<?php

namespace Tests\Feature\Tenant;

use App\Enums\WorkflowStatus;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NegotiationApiTest extends TestCase
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
            'name' => 'Tenant Negotiation Admin',
            'email' => 'tenant-negotiation-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_create_update_and_track_negotiation_events(): void
    {
        $terreno = $this->createNegotiationFixture();

        $createResponse = $this->actingAs($this->admin)->postJson('/api/v1/negociacoes', [
            'terreno_id' => $terreno->id,
            'proposal_value' => 1250000.50,
            'business_model' => 'permuta',
            'notes' => 'Negociação iniciada.',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.terreno_id', $terreno->id)
            ->assertJsonPath('data.status', WorkflowStatus::NEGOCIACAO_MINUTA->value)
            ->assertJsonPath('data.business_model', 'permuta');

        $negotiationId = $createResponse->json('data.id');

        $this->actingAs($this->admin)->putJson("/api/v1/negociacoes/{$negotiationId}", [
            'proposal_value' => 1400000,
            'notes' => 'Proposta revisada após reunião.',
        ])->assertOk()
            ->assertJsonPath('data.proposal_value', 1400000)
            ->assertJsonPath('data.notes', 'Proposta revisada após reunião.');

        $this->actingAs($this->admin)->postJson("/api/v1/negociacoes/{$negotiationId}/events", [
            'event_type' => 'meeting',
            'payload_json' => ['channel' => 'presencial'],
            'notes' => 'Reunião com proprietário.',
        ])->assertCreated()
            ->assertJsonPath('data.event_type', 'meeting')
            ->assertJsonPath('data.payload.channel', 'presencial');

        $this->actingAs($this->admin)->getJson('/api/v1/negociacoes')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);

        $this->actingAs($this->admin)->getJson("/api/v1/negociacoes/{$negotiationId}")
            ->assertOk()
            ->assertJsonPath('data.id', $negotiationId);
    }

    public function test_negotiation_endpoints_require_auth_and_committee_approval(): void
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno sem comitê aprovado',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->postJson('/api/v1/negociacoes', [
            'terreno_id' => $terreno->id,
        ])->assertUnauthorized();

        $this->actingAs($this->admin)->postJson('/api/v1/negociacoes', [
            'terreno_id' => $terreno->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['terreno_id']);
    }

    private function createNegotiationFixture(): Terreno
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno Negociação',
            'workflow_stage' => WorkflowStatus::AGUARDANDO_COMITE->stage(),
            'workflow_status_code' => WorkflowStatus::AGUARDANDO_COMITE->value,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $viabilidade = Viabilidade::create([
            'terreno_id' => $terreno->id,
            'version' => 1,
            'is_current' => true,
            'status' => 'ativo',
            'approval_status' => 'aprovada',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        ComiteRevisao::create([
            'terreno_id' => $terreno->id,
            'viabilidade_id' => $viabilidade->id,
            'status' => 'aprovado_comite',
            'final_decision' => 'aprovado_comite',
            'final_comments' => 'Aprovado para negociação.',
            'decided_by' => $this->admin->id,
            'decided_at' => now(),
            'required_departments' => ['comercial', 'engenharia', 'juridico'],
        ]);

        return $terreno;
    }
}