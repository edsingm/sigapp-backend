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
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContractApiTest extends TestCase
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
            'name' => 'Tenant Contract Admin',
            'email' => 'tenant-contract-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_create_update_sign_and_list_contracts(): void
    {
        ['terreno' => $terreno, 'negociacao' => $negociacao] = $this->createContractFixture();

        $createResponse = $this->actingAs($this->admin)->postJson('/api/v1/contratos', [
            'terreno_id' => $terreno->id,
            'negociacao_id' => $negociacao->id,
            'contract_type' => 'compra',
            'contract_number' => 'CTR-001',
            'file_path' => '/contracts/ctr-001.pdf',
            'notes' => 'Minuta gerada.',
            'partes' => [
                [
                    'name' => 'Proprietário 1',
                    'document' => '12345678900',
                    'party_type' => 'seller',
                ],
            ],
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.terreno_id', $terreno->id)
            ->assertJsonPath('data.contract_number', 'CTR-001')
            ->assertJsonPath('data.status', WorkflowStatus::NEGOCIACAO_MINUTA->value);

        $contractId = $createResponse->json('data.id');

        $this->actingAs($this->admin)->putJson("/api/v1/contratos/{$contractId}", [
            'terreno_id' => $terreno->id,
            'negociacao_id' => $negociacao->id,
            'contract_type' => 'compra',
            'contract_number' => 'CTR-001-A',
            'file_path' => '/contracts/ctr-001-a.pdf',
            'notes' => 'Minuta revisada.',
            'partes' => [
                [
                    'name' => 'Proprietário 1',
                    'document' => '12345678900',
                    'party_type' => 'seller',
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.contract_number', 'CTR-001-A')
            ->assertJsonPath('data.notes', 'Minuta revisada.');

        $this->actingAs($this->admin)->postJson("/api/v1/contratos/{$contractId}/sign")
            ->assertOk()
            ->assertJsonPath('data.status', WorkflowStatus::CONTRATO_ASSINADO->value);

        $this->actingAs($this->admin)->getJson('/api/v1/contratos')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);

        $this->actingAs($this->admin)->getJson("/api/v1/contratos/{$contractId}")
            ->assertOk()
            ->assertJsonPath('data.id', $contractId);
    }

    public function test_contract_endpoints_require_auth_and_validate_signing_prerequisites(): void
    {
        ['terreno' => $terreno] = $this->createContractFixture();

        $this->postJson('/api/v1/contratos', [
            'terreno_id' => $terreno->id,
        ])->assertUnauthorized();

        $createResponse = $this->actingAs($this->admin)->postJson('/api/v1/contratos', [
            'terreno_id' => $terreno->id,
        ])->assertCreated();

        $contractId = $createResponse->json('data.id');

        $this->actingAs($this->admin)->postJson("/api/v1/contratos/{$contractId}/sign")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contract']);
    }

    /**
     * @return array{terreno: Terreno, negociacao: Negociacao}
     */
    private function createContractFixture(): array
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno Contrato',
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

        $negociacao = Negociacao::create([
            'terreno_id' => $terreno->id,
            'status' => WorkflowStatus::NEGOCIACAO_MINUTA->value,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $terreno->update([
            'workflow_stage' => WorkflowStatus::NEGOCIACAO_MINUTA->stage(),
            'workflow_status_code' => WorkflowStatus::NEGOCIACAO_MINUTA->value,
        ]);

        return [
            'terreno' => $terreno->fresh(),
            'negociacao' => $negociacao->fresh(),
        ];
    }
}