<?php

namespace Tests\Feature\Tenant;

use App\Enums\WorkflowStatus;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\Contrato;
use App\Models\Tenant\ContratoParte;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LegalizacaoApiTest extends TestCase
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
            'name' => 'Tenant Legalization Admin',
            'email' => 'tenant-legalization-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_manage_legalizacao_lifecycle(): void
    {
        $terreno = $this->createSignedContractFixture();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/legalizacoes/eligible-terrenos')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);

        $createResponse = $this->actingAs($this->admin)->postJson('/api/v1/legalizacoes', [
            'terreno_id' => $terreno->id,
            'nome' => 'Legalização Terreno A',
            'observacoes' => 'Início do processo.',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.terreno_id', $terreno->id)
            ->assertJsonPath('data.status', 'planejado');

        $legalizacaoId = $createResponse->json('data.id');

        $this->actingAs($this->admin)->getJson('/api/v1/legalizacoes')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);

        $this->actingAs($this->admin)->getJson("/api/v1/legalizacoes/{$legalizacaoId}")
            ->assertOk()
            ->assertJsonPath('data.legalizacao.id', $legalizacaoId);

        $this->actingAs($this->admin)->putJson("/api/v1/legalizacoes/{$legalizacaoId}", [
            'nome' => 'Legalização Terreno A - Revisada',
            'status' => 'em_andamento',
            'percentual_concluido' => 20,
        ])->assertOk()
            ->assertJsonPath('data.nome', 'Legalização Terreno A - Revisada')
            ->assertJsonPath('data.status', 'em_andamento');

        $this->actingAs($this->admin)->postJson("/api/v1/legalizacoes/{$legalizacaoId}/sync-gantt", [
            'etapas' => [
                [
                    'nome' => 'Protocolo Prefeitura',
                    'status' => 'em_andamento',
                    'ordem' => 1,
                    'inicio_planejado' => now()->toDateString(),
                    'fim_planejado' => now()->addDays(5)->toDateString(),
                    'percentual' => 50,
                ],
            ],
        ])->assertOk()
            ->assertJsonStructure(['success', 'data' => ['legalizacao', 'etapas', 'dependencias']]);

        $this->actingAs($this->admin)->postJson("/api/v1/legalizacoes/{$legalizacaoId}/recalcular-progresso")
            ->assertOk()
            ->assertJsonPath('data.id', $legalizacaoId);

        $this->actingAs($this->admin)->deleteJson("/api/v1/legalizacoes/{$legalizacaoId}")
            ->assertNoContent();
    }

    public function test_legalizacao_endpoints_require_auth_and_signed_contract_status(): void
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno sem contrato assinado',
            'workflow_stage' => WorkflowStatus::NEGOCIACAO_MINUTA->stage(),
            'workflow_status_code' => WorkflowStatus::NEGOCIACAO_MINUTA->value,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->postJson('/api/v1/legalizacoes', [
            'terreno_id' => $terreno->id,
        ])->assertUnauthorized();

        $this->actingAs($this->admin)->postJson('/api/v1/legalizacoes', [
            'terreno_id' => $terreno->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['terreno_id']);
    }

    private function createSignedContractFixture(): Terreno
    {
        $terreno = Terreno::create([
            'nome' => 'Terreno Legalização',
            'workflow_stage' => WorkflowStatus::CONTRATO_ASSINADO->stage(),
            'workflow_status_code' => WorkflowStatus::CONTRATO_ASSINADO->value,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $contract = Contrato::create([
            'terreno_id' => $terreno->id,
            'contract_type' => 'compra',
            'contract_number' => 'LGL-001',
            'signed_at' => now(),
            'status' => WorkflowStatus::CONTRATO_ASSINADO->value,
            'file_path' => '/contracts/lgl-001.pdf',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        ContratoParte::create([
            'contrato_id' => $contract->id,
            'name' => 'Proprietário Contrato',
            'document' => '12345678900',
            'party_type' => 'seller',
        ]);

        return $terreno;
    }
}
