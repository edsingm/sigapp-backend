<?php

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Enums\PerfilFinanciamento;
use App\Enums\WorkflowStatus;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\ComiteRevisao;
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
            EnsureTenantContext::class,
            EnsureTenantUser::class,
            EnsureTenantAdmin::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        $this->popularPremissasPadrao();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Tenant Admin',
            'email' => 'tenant-viabilidade-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);
    }

    public function test_admin_can_crud_compare_and_select_viabilidades(): void
    {
        $terrenoProduto = $this->createViabilityFixture();

        $createResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades', $this->makePayload($terrenoProduto));

        $createResponse->assertCreated()
            ->assertJsonPath('data.viabilidade.terreno_id', $terrenoProduto->getAttribute('terreno_id'))
            ->assertJsonPath('data.viabilidade.status', 'rascunho')
            ->assertJsonPath('data.viabilidade.approval_status', 'pendente')
            ->assertJsonStructure([
                'data' => [
                    'viabilidade' => ['id'],
                    'resumo',
                    'indicadores',
                    'produtos_resumo',
                    'calculation_engine_version',
                    'warnings',
                    'reconciliation',
                ],
            ])
            ->assertJsonPath('data.calculation_engine_version', '3.0.0')
            ->assertJsonPath('data.viabilidade.usar_antecipacao_pj', true)
            ->assertJsonPath('data.warnings', [
                'Custo de obra incorrido ultrapassou o orçamento POC.',
            ])
            ->assertJsonPath('data.reconciliation.status', 'ok')
            ->assertJsonMissingPath('data.dre')
            ->assertJsonMissingPath('data.fluxo_mensal');

        $viabilidadeId = $createResponse->json('data.viabilidade.id');

        $this->assertDatabaseHas('viabilidades', [
            'id' => $viabilidadeId,
            'status' => 'rascunho',
            'approval_status' => 'pendente',
            'usar_antecipacao_pj' => true,
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/viabilidades')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);

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
            ->assertJsonPath('data.0.terreno_id', $terrenoProduto->getAttribute('terreno_id'));

        $this->actingAs($this->admin)
            ->getJson('/api/v1/viabilidades/terreno/'.$terrenoProduto->getAttribute('terreno_id').'/latest')
            ->assertOk()
            ->assertJsonPath('data.id', $duplicateId);
    }

    public function test_antecipacao_pj_desativada_preserva_configuracao_e_zera_divida(): void
    {
        $terrenoProduto = $this->createViabilityFixture();
        $payload = [
            ...$this->makePayload($terrenoProduto),
            'usar_antecipacao_pj' => false,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades?include=dre,fluxo_mensal_financeiro,parametros_utilizados', $payload)
            ->assertCreated()
            ->assertJsonPath('data.viabilidade.usar_antecipacao_pj', false)
            ->assertJsonPath('data.viabilidade.percentual_antecipacao_pj', 10)
            ->assertJsonPath('data.parametros_utilizados.usar_antecipacao_pj', false)
            ->assertJsonPath('data.parametros_utilizados.percentual_antecipacao_pj_configurado', 0.1)
            ->assertJsonPath('data.parametros_utilizados.percentual_antecipacao_pj', 0)
            ->assertJsonPath('data.indicadores.divida_pj.valor_antecipado', 0)
            ->assertJsonPath('data.indicadores.divida_pj.juros_totais', 0)
            ->assertJsonPath('data.dre.juros_pj', 0);

        foreach ($response->json('data.fluxo_mensal_financeiro') as $mes) {
            $this->assertSame(0.0, (float) $mes['entrada_antecipacao_pj']);
            $this->assertSame(0.0, (float) $mes['pagamento_pj']);
            $this->assertSame(0.0, (float) $mes['saldo_divida_pj']);
        }

        $viabilidadeId = (int) $response->json('data.viabilidade.id');
        $reativada = $this->actingAs($this->admin)
            ->putJson(
                "/api/v1/viabilidades/{$viabilidadeId}?include=parametros_utilizados",
                $this->makePayload($terrenoProduto),
            )
            ->assertOk()
            ->assertJsonPath('data.viabilidade.usar_antecipacao_pj', true)
            ->assertJsonPath('data.parametros_utilizados.percentual_antecipacao_pj', 0.1);

        $this->assertGreaterThan(
            0,
            (float) $reativada->json('data.indicadores.divida_pj.valor_antecipado'),
        );
    }

    public function test_catalogo_expoe_somente_modelos_selecionaveis(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/viabilidades/modelos-financiamento')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $valores = array_column($response->json('data'), 'value');

        $this->assertSame([
            PerfilFinanciamento::PROPRIO->value,
            PerfilFinanciamento::APOIO_PRODUCAO->value,
            PerfilFinanciamento::PLANO_EMPRESARIO->value,
            PerfilFinanciamento::ALOCACAO_RECURSOS->value,
        ], $valores);
        $this->assertNotContains(PerfilFinanciamento::CEF->value, $valores);
    }

    public function test_nova_viabilidade_exige_modelo_e_rejeita_financiamento_pj_quando_incompativel(): void
    {
        $terrenoProduto = $this->createViabilityFixture();
        $payload = $this->makePayload($terrenoProduto);

        unset($payload['perfil_financiamento']);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['perfil_financiamento']);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades', [
                ...$payload,
                'perfil_financiamento' => PerfilFinanciamento::ALOCACAO_RECURSOS->value,
                'usar_antecipacao_pj' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['usar_antecipacao_pj']);
    }

    public function test_calculation_metadata_has_safe_fallbacks_for_legacy_snapshots(): void
    {
        $terrenoProduto = $this->createViabilityFixture();
        $viabilidade = Viabilidade::create([
            'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
            'version' => 1,
            'is_current' => true,
            'status' => 'ativo',
            'approval_status' => 'aprovada',
            'resultados_dre' => [
                'vgv' => 1_000_000,
                'totais' => ['receita' => 1_000_000],
                'indicadores' => ['margem_liquida_percentual' => 20],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/viabilidades/{$viabilidade->id}")
            ->assertOk()
            ->assertJsonPath('data.calculation_engine_version', null)
            ->assertJsonPath('data.warnings', [])
            ->assertJsonPath('data.reconciliation', null);
    }

    public function test_version_is_not_reused_after_soft_delete_and_deleted_viabilidade_can_be_restored(): void
    {
        $terrenoProduto = $this->createViabilityFixture();

        $firstResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades', $this->makePayload($terrenoProduto))
            ->assertCreated()
            ->assertJsonPath('data.viabilidade.version', 1);

        $firstId = (int) $firstResponse->json('data.viabilidade.id');

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/viabilidades/{$firstId}")
            ->assertOk();

        $secondResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades', $this->makePayload($terrenoProduto))
            ->assertCreated()
            ->assertJsonPath('data.viabilidade.version', 2);

        $secondId = (int) $secondResponse->json('data.viabilidade.id');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/viabilidades/{$firstId}/restore")
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.is_current', false);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/viabilidades/terreno/'.$terrenoProduto->getAttribute('terreno_id').'/latest')
            ->assertOk()
            ->assertJsonPath('data.id', $secondId);
    }

    public function test_admin_can_submit_approve_and_recalculate_viabilidade(): void
    {
        $terrenoProduto = $this->createViabilityFixture();
        $viabilidade = Viabilidade::create([
            'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
            'version' => 1,
            'is_current' => true,
            'status' => 'rascunho',
            'approval_status' => 'pendente',
            'resultados_dre' => [
                'vgv' => 1_000_000,
                'totalUnidades' => 12,
                'totais' => ['receita' => 1_000_000],
                'indicadores' => ['margem_liquida_percentual' => 20],
                'reconciliation' => ['status' => 'ok'],
            ],
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

        // Recálculo de aprovada cria nova versão e preserva a original.
        $recalc = $this->actingAs($this->admin)
            ->postJson("/api/v1/viabilidades/{$viabilidade->id}/recalcular")
            ->assertOk()
            ->assertJsonStructure(['data' => ['resumo', 'indicadores', 'produtos_resumo']])
            ->assertJsonMissingPath('data.dre')
            ->json('data.viabilidade.id');

        $this->assertNotEquals($viabilidade->id, $recalc);
        $this->assertDatabaseHas('viabilidades', [
            'id' => $viabilidade->id,
            'approval_status' => 'aprovada',
        ]);
        $this->assertDatabaseHas('viabilidades', [
            'id' => $recalc,
            'approval_status' => 'pendente',
            'terreno_id' => $viabilidade->terreno_id,
        ]);

        $this->assertDatabaseHas('viabilidade_aprovacoes', [
            'viabilidade_id' => $viabilidade->id,
            'decision' => 'aprovada',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_reject_viabilidade_com_status_canonico_rejeitada(): void
    {
        $terrenoProduto = $this->createViabilityFixture();
        $viabilidade = Viabilidade::create([
            'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
            'version' => 1,
            'is_current' => true,
            'status' => 'rascunho',
            'approval_status' => 'em_aprovacao',
            'resultados_dre' => [
                'vgv' => 500_000,
                'totais' => ['receita' => 500_000],
                'indicadores' => [],
                'reconciliation' => ['status' => 'ok'],
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/viabilidades/{$viabilidade->id}/reprovar", [
                'approval_notes' => 'Premissas inconsistentes com o terreno.',
            ])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'rejeitada')
            ->assertJsonPath('data.status', 'rascunho')
            ->assertJsonPath('data.allowed_actions', [
                'duplicate',
                'recalculate_as_new_version',
            ]);

        $this->assertDatabaseHas('viabilidades', [
            'id' => $viabilidade->id,
            'approval_status' => 'rejeitada',
            'status' => 'rascunho',
        ]);

        $this->assertDatabaseHas('viabilidade_aprovacoes', [
            'viabilidade_id' => $viabilidade->id,
            'decision' => 'rejeitada',
            'user_id' => $this->admin->id,
        ]);

        // Versão rejeitada permanece imutável.
        $this->actingAs($this->admin)
            ->putJson("/api/v1/viabilidades/{$viabilidade->id}", $this->makePayload($terrenoProduto))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VIABILIDADE_LOCKED');
    }

    public function test_nao_permite_editar_viabilidade_aprovada(): void
    {
        $terrenoProduto = $this->createViabilityFixture();
        $viabilidade = Viabilidade::create([
            'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
            'version' => 1,
            'is_current' => true,
            'status' => 'ativo',
            'approval_status' => 'aprovada',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/viabilidades/{$viabilidade->id}", $this->makePayload($terrenoProduto))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VIABILIDADE_LOCKED');
    }

    public function test_nao_permite_editar_nem_recalcular_viabilidade_em_aprovacao(): void
    {
        $terrenoProduto = $this->createViabilityFixture();
        $viabilidade = Viabilidade::create([
            'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
            'version' => 1,
            'is_current' => true,
            'status' => 'rascunho',
            'approval_status' => 'em_aprovacao',
            'resultados_dre' => ['vgv' => 1, 'totais' => ['receita' => 1], 'indicadores' => []],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/viabilidades/{$viabilidade->id}", $this->makePayload($terrenoProduto))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VIABILIDADE_LOCKED');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/viabilidades/{$viabilidade->id}/recalcular")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VIABILIDADE_LOCKED');
    }

    public function test_nao_permite_submeter_viabilidade_ja_em_aprovacao_ou_aprovada(): void
    {
        $terrenoProduto = $this->createViabilityFixture();
        $viabilidade = Viabilidade::create([
            'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
            'version' => 1,
            'is_current' => true,
            'status' => 'rascunho',
            'approval_status' => 'em_aprovacao',
            'resultados_dre' => ['vgv' => 1],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/viabilidades/{$viabilidade->id}/solicitar-aprovacao", [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VIABILIDADE_SUBMIT_NOT_ALLOWED');

        $viabilidade->update(['approval_status' => 'aprovada', 'status' => 'ativo']);

        $this->actingAs($this->admin)
            ->postJson("/api/v1/viabilidades/{$viabilidade->id}/solicitar-aprovacao", [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VIABILIDADE_SUBMIT_NOT_ALLOWED');
    }

    public function test_apenas_diretor_pode_revogar_aprovacao(): void
    {
        $terrenoProduto = $this->createViabilityFixture();
        $viabilidade = Viabilidade::create([
            'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
            'version' => 1,
            'is_current' => true,
            'status' => 'ativo',
            'approval_status' => 'aprovada',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        // Admin (não Diretor) não pode revogar.
        $this->actingAs($this->admin)
            ->postJson("/api/v1/viabilidades/{$viabilidade->id}/revogar-aprovacao", [])
            ->assertForbidden();

        // Diretor pode revogar: estado explícito revogada; edição exige nova versão.
        $diretor = $this->makeDiretor();
        $this->actingAs($diretor)
            ->postJson("/api/v1/viabilidades/{$viabilidade->id}/revogar-aprovacao", [
                'approval_notes' => 'Revisão de premissas necessária.',
            ])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'revogada')
            ->assertJsonPath('data.status', 'rascunho');

        $this->assertDatabaseHas('viabilidade_aprovacoes', [
            'viabilidade_id' => $viabilidade->id,
            'decision' => 'revogada',
            'user_id' => $diretor->id,
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/viabilidades/{$viabilidade->id}", $this->makePayload($terrenoProduto))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VIABILIDADE_LOCKED');
    }

    public function test_revogar_bloqueado_com_comite_aberto(): void
    {
        $terrenoProduto = $this->createViabilityFixture();
        $terrenoId = $terrenoProduto->getAttribute('terreno_id');
        $viabilidade = Viabilidade::create([
            'terreno_id' => $terrenoId,
            'version' => 1,
            'is_current' => true,
            'status' => 'ativo',
            'approval_status' => 'aprovada',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        ComiteRevisao::create([
            'terreno_id' => $terrenoId,
            'viabilidade_id' => $viabilidade->id,
            'status' => WorkflowStatus::AGUARDANDO_COMITE->value,
            'required_departments' => ['comercial'],
        ]);

        $diretor = $this->makeDiretor();
        $this->actingAs($diretor)
            ->postJson("/api/v1/viabilidades/{$viabilidade->id}/revogar-aprovacao", [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VIABILIDADE_COMMITTEE_PENDING');
    }

    private function makeDiretor(): User
    {
        Role::query()->firstOrCreate(['name' => RolesEnum::DIRECTOR->value, 'guard_name' => 'web']);

        $diretor = User::create([
            'name' => 'Tenant Diretor',
            'email' => 'tenant-viabilidade-diretor@test.com',
            'password' => Hash::make('password123'),
        ]);
        $diretor->assignRole(RolesEnum::DIRECTOR);

        return $diretor;
    }

    public function test_terreno_pode_ter_apenas_uma_viabilidade_aprovada(): void
    {
        $terrenoProduto = $this->createViabilityFixture();
        $terrenoId = $terrenoProduto->getAttribute('terreno_id');

        $aprovada = Viabilidade::create([
            'terreno_id' => $terrenoId,
            'version' => 1,
            'is_current' => true,
            'status' => 'ativo',
            'approval_status' => 'aprovada',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        // Novo estudo não rouba o is_current enquanto houver uma aprovada.
        $nova = Viabilidade::create([
            'terreno_id' => $terrenoId,
            'version' => 2,
            'is_current' => false,
            'status' => 'rascunho',
            'approval_status' => 'em_aprovacao',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        // Aprovar a segunda é bloqueado.
        $this->actingAs($this->admin)
            ->postJson("/api/v1/viabilidades/{$nova->id}/aprovar", [
                'approval_notes' => 'Tentando aprovar a segunda.',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'VIABILIDADE_ALREADY_APPROVED_FOR_TERRENO');

        // A viabilidade aprovada continua sendo a atual (is_current) do terreno.
        $this->assertDatabaseHas('viabilidades', ['id' => $aprovada->id, 'is_current' => true]);
        $this->assertDatabaseHas('viabilidades', ['id' => $nova->id, 'is_current' => false]);
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
                'perfil_financiamento' => PerfilFinanciamento::APOIO_PRODUCAO->value,
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
                'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
                'perfil_financiamento' => PerfilFinanciamento::APOIO_PRODUCAO->value,
                'medicao_contratacao' => 48000,
                'custo_medicao_cef' => 1250,
                'produtos' => [
                    [
                        'id' => $terrenoProduto->getKey(),
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
                'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
                'perfil_financiamento' => PerfilFinanciamento::APOIO_PRODUCAO->value,
                'gastos_mensais_stand' => 0.1234,
                'comissao_house_percentual' => 4.5,
                'comissao_imobiliarias_percentual' => 5.5,
                'percentual_vendas_house' => 60.0,
                'pagamento_comissao_venda' => 65.0,
                'marketing_lancamento' => 30.0,
                'marketing_inicio_antes_lancamento' => 5,
                'produtos' => [
                    [
                        'id' => $terrenoProduto->getKey(),
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

    public function test_show_retorna_payload_completo_para_edicao_da_viabilidade(): void
    {
        $terrenoProduto = $this->createViabilityFixture();
        $viabilidade = Viabilidade::create([
            'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
            'version' => 1,
            'is_current' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'premissas_snapshot' => [
                'form_values' => [
                    'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
                    'meses_entrega' => 4,
                    'meses_pos_obra' => 36,
                    'variavel_correcao' => 2.7545,
                    'incorp_ri' => 31.5,
                    'incorp_entrega' => 14.25,
                    'incorp_ate_lancamento' => 82.0,
                    'obra_ate_lancamento' => 3.5,
                    'parcelamento_comissao_terreno' => 9,
                    'taxa_juros_pj' => 11.75,
                    'carencia_pj_meses' => 7,
                    'amortizacao_pj_parcelas' => 21,
                    'inadimplencia' => 12.5,
                    'atraso_meses' => 3,
                    'taxa_perda' => 2.25,
                    'produtos' => [
                        [
                            'id' => $terrenoProduto->getKey(),
                            'unidades' => 13,
                            'valor' => 265000,
                            'permuta' => 15000,
                            'pgto_por_lote' => 4200,
                            'custo_m2' => 1950,
                            'custo_infra' => 330,
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/viabilidades/{$viabilidade->id}")
            ->assertOk()
            ->assertJsonPath('data.viabilidade.meses_entrega', 4)
            ->assertJsonPath('data.viabilidade.meses_pos_obra', 36)
            ->assertJsonPath('data.viabilidade.variavel_correcao', 2.7545)
            ->assertJsonPath('data.viabilidade.incorp_ri', 31.5)
            ->assertJsonPath('data.viabilidade.incorp_entrega', 14.25)
            ->assertJsonPath('data.viabilidade.incorp_ate_lancamento', 82)
            ->assertJsonPath('data.viabilidade.obra_ate_lancamento', 3.5)
            ->assertJsonPath('data.viabilidade.parcelamento_comissao_terreno', 9)
            ->assertJsonPath('data.viabilidade.taxa_juros_pj', 11.75)
            ->assertJsonPath('data.viabilidade.carencia_pj_meses', 7)
            ->assertJsonPath('data.viabilidade.amortizacao_pj_parcelas', 21)
            ->assertJsonPath('data.viabilidade.inadimplencia', 12.5)
            ->assertJsonPath('data.viabilidade.atraso_meses', 3)
            ->assertJsonPath('data.viabilidade.taxa_perda', 2.25)
            ->assertJsonPath('data.viabilidade.produtos.0.id', $terrenoProduto->getKey())
            ->assertJsonPath('data.viabilidade.produtos.0.unidades', 13)
            ->assertJsonPath('data.viabilidade.produtos.0.valor', 265000)
            ->assertJsonPath('data.viabilidade.produtos.0.permuta', 15000)
            ->assertJsonPath('data.viabilidade.produtos.0.pgto_por_lote', 4200)
            ->assertJsonPath('data.viabilidade.produtos.0.custo_m2', 1950)
            ->assertJsonPath('data.viabilidade.produtos.0.custo_infra', 330);
    }

    public function test_update_preserva_snapshot_anterior_para_auditoria_de_viabilidade(): void
    {
        $terrenoProduto = $this->createViabilityFixture();

        $createResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades', [
                'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
                'perfil_financiamento' => PerfilFinanciamento::APOIO_PRODUCAO->value,
                'compra_terreno' => 1000000,
                'produtos' => [
                    [
                        'id' => $terrenoProduto->getKey(),
                        'unidades' => 12,
                        'valor' => 250000,
                        'permuta' => 0,
                        'pgto_por_lote' => 0,
                        'custo_m2' => 1800,
                        'custo_infra' => 300,
                    ],
                ],
            ]);

        $viabilidadeId = (int) $createResponse->json('data.viabilidade.id');

        $this->actingAs($this->admin)
            ->putJson("/api/v1/viabilidades/{$viabilidadeId}", [
                'compra_terreno' => 1250000,
                'produtos' => [
                    [
                        'id' => $terrenoProduto->getKey(),
                        'unidades' => 12,
                        'valor' => 250000,
                        'permuta' => 0,
                        'pgto_por_lote' => 0,
                        'custo_m2' => 1800,
                        'custo_infra' => 300,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.viabilidade.compra_terreno', 1250000);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/viabilidades/{$viabilidadeId}?include=auditoria,premissas_snapshot")
            ->assertOk()
            ->assertJsonPath('data.viabilidade.compra_terreno', 1250000)
            // Snapshot canônico (schema v2) persiste after_form_values como form_values.
            ->assertJsonPath(
                'data.viabilidade.premissas_snapshot.form_values.compra_terreno',
                1250000
            )
            ->assertJsonPath(
                'data.viabilidade.premissas_snapshot.schema_version',
                2
            )
            ->assertJsonPath(
                'data.viabilidade.premissas_snapshot.historico.0.before_form_values.compra_terreno',
                '1000000.00'
            )
            ->assertJsonPath(
                'data.viabilidade.premissas_snapshot.historico.0.after_form_values.compra_terreno',
                1250000
            )
            ->assertJsonPath(
                'data.viabilidade.premissas_snapshot.alterado_por_user.name',
                $this->admin->name
            )
            ->assertJsonPath(
                'data.viabilidade.premissas_snapshot.referencia_atualizada_por_user.name',
                $this->admin->name
            );
    }

    public function test_update_acumula_historico_e_registra_alteracoes_de_produto(): void
    {
        $terrenoProduto = $this->createViabilityFixture();

        $createResponse = $this->actingAs($this->admin)
            ->postJson('/api/v1/viabilidades', [
                'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
                'perfil_financiamento' => PerfilFinanciamento::APOIO_PRODUCAO->value,
                'produtos' => [
                    [
                        'id' => $terrenoProduto->getKey(),
                        'unidades' => 12,
                        'valor' => 250000,
                        'permuta' => 0,
                        'pgto_por_lote' => 0,
                        'custo_m2' => 1800,
                        'custo_infra' => 300,
                    ],
                ],
            ]);

        $viabilidadeId = (int) $createResponse->json('data.viabilidade.id');

        $this->actingAs($this->admin)
            ->putJson("/api/v1/viabilidades/{$viabilidadeId}", [
                'compra_terreno' => 1250000,
                'produtos' => [
                    [
                        'id' => $terrenoProduto->getKey(),
                        'unidades' => 12,
                        'valor' => 250000,
                        'permuta' => 0,
                        'pgto_por_lote' => 0,
                        'custo_m2' => 1800,
                        'custo_infra' => 300,
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/viabilidades/{$viabilidadeId}", [
                'produtos' => [
                    [
                        'id' => $terrenoProduto->getKey(),
                        'unidades' => 12,
                        'valor' => 275000,
                        'permuta' => 0,
                        'pgto_por_lote' => 0,
                        'custo_m2' => 1800,
                        'custo_infra' => 330,
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->getJson("/api/v1/viabilidades/{$viabilidadeId}?include=premissas_snapshot")
            ->assertOk()
            ->assertJsonCount(2, 'data.viabilidade.premissas_snapshot.historico')
            ->assertJsonPath(
                'data.viabilidade.premissas_snapshot.historico.1.after_form_values.produtos.0.valor',
                275000
            )
            ->assertJsonPath(
                'data.viabilidade.premissas_snapshot.historico.1.after_form_values.produtos.0.custo_infra',
                330
            );
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
            'terreno_id' => $terrenoProduto->getAttribute('terreno_id'),
            'perfil_financiamento' => PerfilFinanciamento::APOIO_PRODUCAO->value,
            'prazo_obra' => 18,
            'usar_antecipacao_pj' => true,
            'taxa_juros_pj' => 10.5,
            'carencia_pj_meses' => 6,
            'amortizacao_pj_parcelas' => 18,
            'percentual_antecipacao_pj' => 10,
            'produtos' => [
                [
                    'id' => $terrenoProduto->getKey(),
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
