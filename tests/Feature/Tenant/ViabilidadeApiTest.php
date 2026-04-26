<?php

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\Produto;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ViabilidadeApiTest extends TestCase
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
            'email' => 'tenant-viabilidade-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_crud_compare_and_select_viabilidades(): void
    {
        $terrenoProduto = $this->createViabilityFixture();

        $createResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades', $this->makePayload($terrenoProduto));

        $createResponse->assertCreated()
            ->assertJsonPath('data.viabilidade.terreno_id', $terrenoProduto->terreno_id)
            ->assertJsonStructure(['data' => ['viabilidade' => ['id'], 'dre_resultados']]);

        $viabilidadeId = $createResponse->json('data.viabilidade.id');

        $this->actingAs($this->admin)
            ->getJson('/api/v1/viabilidades')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/viabilidades/{$viabilidadeId}")
            ->assertOk()
            ->assertJsonPath('data.viabilidade.id', $viabilidadeId);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/viabilidades/{$viabilidadeId}", [
                'prazo_obra' => 24,
                'produtos' => [
                    [
                        'id' => $terrenoProduto->id,
                        'unidades' => 18,
                        'valor' => 260000,
                        'permuta' => 0,
                        'pgto_por_lote' => 0,
                        'custo_m2' => 1800,
                        'custo_infra' => 300,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.viabilidade.prazo_obra', 24);

        $duplicateResponse = $this->actingAs($this->admin)
            ->postJson("/api/v1/viabilidades/{$viabilidadeId}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.version', 2);

        $duplicateId = $duplicateResponse->json('data.id');

        $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades/compare', [
                'viabilidade_1_id' => $viabilidadeId,
                'viabilidade_2_id' => $duplicateId,
            ])
            ->assertOk()
            ->assertJsonPath('data.viabilidade_1.viabilidade.id', $viabilidadeId)
            ->assertJsonPath('data.viabilidade_2.viabilidade.id', $duplicateId);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/viabilidades/for-select')
            ->assertOk()
            ->assertJsonPath('data.0.terreno_id', $terrenoProduto->terreno_id);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/viabilidades/terreno/{$terrenoProduto->terreno_id}/latest")
            ->assertOk()
            ->assertJsonPath('data.id', $duplicateId);
    }

    public function test_admin_can_submit_approve_and_recalculate_viabilidade(): void
    {
        $terrenoProduto = $this->createViabilityFixture();
        $viabilidade = Viabilidade::create([
            'terreno_id' => $terrenoProduto->terreno_id,
            'version' => 1,
            'is_current' => true,
            'status' => 'rascunho',
            'approval_status' => 'pendente',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/viabilidades/{$viabilidade->id}/solicitar-aprovacao", [
                'approval_notes' => 'Enviar para aprovação do comitê financeiro.',
            ])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'em_aprovacao');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/viabilidades/{$viabilidade->id}/aprovar", [
                'approval_notes' => 'Aprovada após revisão.',
            ])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'aprovada')
            ->assertJsonPath('data.status', 'ativo');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/viabilidades/{$viabilidade->id}/recalcular")
            ->assertOk()
            ->assertJsonPath('data.viabilidade.id', $viabilidade->id)
            ->assertJsonStructure(['data' => ['dre_resultados']]);

        $this->assertDatabaseHas('viabilidade_aprovacoes', [
            'viabilidade_id' => $viabilidade->id,
            'decision' => 'aprovada',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_viabilidade_requests_require_authentication_and_valid_payloads(): void
    {
        $this->createViabilityFixture();
        $terrenoSemProduto = Terreno::create([
            'nome' => 'Terreno Sem Produto',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->postJson('/api/v1/viabilidades', [])
            ->assertUnauthorized();

        $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades', [
                'terreno_id' => $terrenoSemProduto->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['terreno_id']);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades/compare', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['viabilidade_1_id', 'viabilidade_2_id']);
    }

    private function createViabilityFixture(): TerrenoProduto
    {
        $corretor = CorretorExterno::create([
            'nome' => 'Corretor Teste',
            'email' => 'corretor-viabilidade@test.com',
            'telefone' => '11999999999',
            'creci' => '12345',
        ]);

        $terreno = Terreno::create([
            'nome' => 'Terreno Viabilidade',
            'corretor_id' => $corretor->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Proprietario::create([
            'terreno_id' => $terreno->id,
            'nome' => 'Proprietário Teste',
            'tipo_pessoa' => 'fisica',
            'porcentagem_terreno' => 100,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $produto = Produto::create([
            'name' => 'Lote Urbanizado',
            'status' => 'ativo',
            'private_area' => 60,
            'm2_cost' => 1800,
            'infra_cost' => 300,
            'curva_vendas' => [10, 20, 20, 20, 15, 15],
        ]);

        return TerrenoProduto::create([
            'terreno_id' => $terreno->id,
            'produto_id' => $produto->id,
            'unidades' => 12,
            'valor' => 250000,
            'permuta' => 0,
            'pgto_por_lote' => 0,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function makePayload(TerrenoProduto $terrenoProduto): array
    {
        return [
            'terreno_id' => $terrenoProduto->terreno_id,
            'prazo_obra' => 18,
            'produtos' => [
                [
                    'id' => $terrenoProduto->id,
                    'unidades' => 12,
                    'valor' => 250000,
                    'permuta' => 0,
                    'pgto_por_lote' => 0,
                    'custo_m2' => 1800,
                    'custo_infra' => 300,
                ],
            ],
        ];
    }
}
