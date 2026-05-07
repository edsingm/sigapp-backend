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
use Illuminate\Support\Facades\DB;
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

        $this->popularPremissasPadrao();

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
            ->assertJsonStructure([
                'data' => [
                    'viabilidade' => ['id'],
                    'resumo',
                    'indicadores',
                    'produtos_resumo',
                ],
            ])
            ->assertJsonMissingPath('data.dre')
            ->assertJsonMissingPath('data.fluxo_mensal');

        $viabilidadeId = $createResponse->json('data.viabilidade.id');

        $this->actingAs($this->admin)
            ->getJson('/api/v1/viabilidades')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta']);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/viabilidades/{$viabilidadeId}")
            ->assertOk()
            ->assertJsonPath('data.viabilidade.id', $viabilidadeId)
            ->assertJsonMissingPath('data.dre')
            ->assertJsonMissingPath('data.fluxo_mensal');

        $this->actingAs($this->admin)
            ->getJson("/api/v1/viabilidades/{$viabilidadeId}?include=dre,fluxo_mensal,parametros_utilizados")
            ->assertOk()
            ->assertJsonPath('data.viabilidade.id', $viabilidadeId)
            ->assertJsonStructure([
                'data' => [
                    'dre',
                    'fluxo_mensal',
                    'parametros_utilizados',
                ],
            ]);

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
            ->assertJsonStructure(['data' => ['resumo', 'indicadores', 'produtos_resumo']])
            ->assertJsonMissingPath('data.dre');

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

    public function test_viabilidade_response_supports_auditoria_and_cef_contract_aliases(): void
    {
        $terrenoProduto = $this->createViabilityFixture();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades?include=auditoria', [
                'terreno_id' => $terrenoProduto->terreno_id,
                'medicao_contratacao' => 48000,
                'custo_medicao_cef' => 1250,
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
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.viabilidade.custo_contratacao_cef', 48000)
            ->assertJsonPath('data.viabilidade.custo_medicao_cef', 1250)
            ->assertJsonStructure(['data' => ['viabilidade' => ['auditoria']]]);
    }

    public function test_viabilidade_persiste_overrides_comerciais_detalhados(): void
    {
        $terrenoProduto = $this->createViabilityFixture();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades', [
                'terreno_id' => $terrenoProduto->terreno_id,
                'gastos_mensais_stand' => 0.1234,
                'comissao_house_percentual' => 4.5,
                'comissao_imobiliarias_percentual' => 5.5,
                'percentual_vendas_house' => 60.0,
                'pagamento_comissao_venda' => 65.0,
                'marketing_lancamento' => 30.0,
                'marketing_inicio_antes_lancamento' => 5,
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
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.viabilidade.gastos_mensais_stand', 0.1234)
            ->assertJsonPath('data.viabilidade.comissao_house_percentual', 4.5)
            ->assertJsonPath('data.viabilidade.comissao_imobiliarias_percentual', 5.5)
            ->assertJsonPath('data.viabilidade.percentual_vendas_house', 60)
            ->assertJsonPath('data.viabilidade.pagamento_comissao_venda', 65)
            ->assertJsonPath('data.viabilidade.marketing_lancamento', 30)
            ->assertJsonPath('data.viabilidade.marketing_inicio_antes_lancamento', 5);

        $viabilidade = Viabilidade::findOrFail($response->json('data.viabilidade.id'));

        $this->assertSame(0.1234, (float) $viabilidade->gastos_mensais_stand);
        $this->assertSame(4.5, (float) $viabilidade->comissao_house_percentual);
        $this->assertSame(5.5, (float) $viabilidade->comissao_imobiliarias_percentual);
        $this->assertSame(60.0, (float) $viabilidade->percentual_vendas_house);
        $this->assertSame(65.0, (float) $viabilidade->pagamento_comissao_venda);
        $this->assertSame(30.0, (float) $viabilidade->marketing_lancamento);
        $this->assertSame(5, (int) $viabilidade->marketing_inicio_antes_lancamento);
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

    private function popularPremissasPadrao(): void
    {
        $agora = now();

        DB::table('premissas_viabilidade')->insert([
            'nome' => 'Padrão CEF (teste)',
            'perfil_financiamento' => 'cef',
            'ativo' => true,
            'vigente_em' => $agora->toDateString(),
            'versao' => 1,
            'pis_cofins' => 4.0,
            'iss' => 0.0,
            'outros_impostos' => 0.5,
            'comissao' => 0.0,
            'parceria_vgv' => 0.0,
            'infra_nao_incidente' => 1.0,
            'incorporacao' => 1.0,
            'incorp_ri' => 30.0,
            'incorp_entrega' => 15.0,
            'incorp_ate_lancamento' => 80.0,
            'obra_ate_lancamento' => 1.0,
            'area_comum' => 0.0,
            'contrapartidas' => 0.0,
            'canteiro_mensal' => 85715.0,
            'mo_administrativa' => 62502.0,
            'seguros' => 0.5,
            'assistencia_tecnica' => 1.0,
            'despesas_comerciais' => 5.0,
            'stand_vendas' => 0.0,
            'mobilia_decoracao' => 90000.0,
            'gastos_mensais_stand' => 0.0001,
            'comissao_house_percentual' => 3.0,
            'comissao_imobiliarias_percentual' => 3.5,
            'percentual_vendas_house' => 50.0,
            'ajuda_custo_gerente' => 5000.0,
            'ajuda_custo_gerente_regional' => 2733.0,
            'reembolso_logistica' => 5000.0,
            'bonus_cca' => 350.0,
            'bonus_gerente' => 0.3,
            'bonus_gerente_regional' => 0.12,
            'bonus_credito' => 0.05,
            'bonus_gestor_comercial' => 0.05,
            'pagamento_comissao_venda' => 50.0,
            'pagamento_comissao_desligamento' => 50.0,
            'parcelamento_comissao_meses' => 18,
            'marketing' => 1.0,
            'marketing_lancamento' => 25.0,
            'marketing_inicio_antes_lancamento' => 3,
            'itbi_iptu' => 1.1,
            'registro' => 2500.0,
            'custo_contratacao_cef' => 0.0,
            'custo_medicao_cef' => 0.0,
            'contratos_cef' => 300.0,
            'produtos_cef' => 0.5,
            'outras_despesas_financeiras' => 0.3,
            'despesas_onerosas_bancos' => 10.0,
            'prazo_obra' => 36,
            'compra_terreno' => 0.0,
            'porcentagem_lote_proprietario' => 10.0,
            'taxa_juros_pj' => 10.5,
            'carencia_pj_meses' => 6,
            'amortizacao_pj_parcelas' => 18,
            'percentual_antecipacao_pj' => 10.0,
            'aporte_adicional_mensal' => 0.0,
            'devolucao_aporte_percentual' => 20.0,
            'distribuicao_lucros_percentual_obra' => 100.0,
            'taxa_exposicao_aplicada' => 12.5,
            'inadimplencia' => 0.10,
            'atraso_meses' => 2,
            'taxa_perda' => 0.02,
            'meses_incorporacao' => 18,
            'meses_lancamento' => 6,
            'meses_entrega' => 1,
            'meses_pos_obra' => 60,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);
    }
}
