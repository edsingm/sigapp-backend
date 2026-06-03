<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Contrato;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\PremissasViabilidade;
use App\Models\Tenant\Produto;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User as TenantUser;
use App\Models\Tenant\Viabilidade;
use App\Models\User as CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Smoke test que garante que todas as factories criam registros válidos.
 *
 * Roda as migrations tenant (o RefreshDatabase cobre as central) e cria
 * um registro de cada factory com seus estados padrão, validando que o
 * model é persistido e que o ID é gerado.
 */
class FactoriesSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
        ]);
    }

    public function test_tenant_user_factory_creates_valid_user(): void
    {
        $user = TenantUser::factory()->createOne();

        $this->assertNotNull($user->id);
        $this->assertNotEmpty($user->name);
        $this->assertNotEmpty($user->email);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
    }

    public function test_terreno_factory_creates_valid_terreno(): void
    {
        $terreno = Terreno::factory()->createOne();

        $this->assertNotNull($terreno->id);
        $this->assertNotEmpty($terreno->nome);
        $this->assertDatabaseHas('terrenos', ['id' => $terreno->id, 'nome' => $terreno->nome]);
    }

    public function test_viabilidade_factory_creates_valid_viabilidade(): void
    {
        $viabilidade = Viabilidade::factory()->createOne();

        $this->assertNotNull($viabilidade->id);
        $this->assertNotNull($viabilidade->terreno_id);
        $this->assertDatabaseHas('viabilidades', ['id' => $viabilidade->id]);
    }

    public function test_negociacao_factory_creates_valid_negociacao(): void
    {
        $negociacao = Negociacao::factory()->createOne();

        $this->assertNotNull($negociacao->id);
        $this->assertNotNull($negociacao->terreno_id);
        $this->assertDatabaseHas('negociacoes', ['id' => $negociacao->id]);
    }

    public function test_contrato_factory_creates_valid_contrato(): void
    {
        $contrato = Contrato::factory()->createOne();

        $this->assertNotNull($contrato->id);
        $this->assertNotNull($contrato->terreno_id);
        $this->assertNotNull($contrato->negociacao_id);
        $this->assertDatabaseHas('contratos', ['id' => $contrato->id]);
    }

    public function test_comite_revisao_factory_creates_valid_comite_revisao(): void
    {
        $comite = ComiteRevisao::factory()->createOne();

        $this->assertNotNull($comite->id);
        $this->assertNotNull($comite->terreno_id);
        $this->assertDatabaseHas('comite_revisoes', ['id' => $comite->id]);
    }

    public function test_produto_factory_creates_valid_produto(): void
    {
        $produto = Produto::factory()->createOne();

        $this->assertNotNull($produto->id);
        $this->assertNotEmpty($produto->name);
        $this->assertDatabaseHas('produtos', ['id' => $produto->id]);
    }

    public function test_proprietario_factory_creates_valid_proprietario(): void
    {
        $proprietario = Proprietario::factory()->createOne();

        $this->assertNotNull($proprietario->id);
        $this->assertNotEmpty($proprietario->nome);
        $this->assertDatabaseHas('terreno_proprietarios', ['id' => $proprietario->id]);
    }

    public function test_task_factory_creates_valid_task(): void
    {
        $task = Task::factory()->createOne();

        $this->assertNotNull($task->id);
        $this->assertNotEmpty($task->title);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_premissas_viabilidade_factory_creates_valid_premissa(): void
    {
        $premissa = PremissasViabilidade::factory()->createOne();

        $this->assertNotNull($premissa->id);
        $this->assertDatabaseHas('premissas_viabilidade', ['id' => $premissa->id]);
    }

    public function test_legalizacao_factory_creates_valid_legalizacao(): void
    {
        $legalizacao = Legalizacao::factory()->createOne();

        $this->assertNotNull($legalizacao->id);
        $this->assertNotNull($legalizacao->terreno_id);
        $this->assertDatabaseHas('legalizacoes', ['id' => $legalizacao->id]);
    }

    public function test_legalizacao_etapa_factory_creates_valid_etapa(): void
    {
        $etapa = LegalizacaoEtapa::factory()->createOne();

        $this->assertNotNull($etapa->id);
        $this->assertNotNull($etapa->legalizacao_id);
        $this->assertDatabaseHas('legalizacao_etapas', ['id' => $etapa->id]);
    }

    public function test_central_user_factory_creates_valid_central_user(): void
    {
        $user = CentralUser::factory()->admin()->createOne();

        $this->assertNotNull($user->id);
        $this->assertTrue((bool) $user->is_admin);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
    }

    // --- Validação de estados ---

    public function test_terreno_aprovado_state_sets_workflow_correctly(): void
    {
        $terreno = Terreno::factory()->aprovado()->createOne();

        $this->assertSame('viabilidade', $terreno->workflow_stage);
        $this->assertSame('viabilidade_aprovada', $terreno->workflow_status_code);
    }

    public function test_terreno_descartado_state_sets_workflow_correctly(): void
    {
        $terreno = Terreno::factory()->descartado()->createOne();

        $this->assertSame('encerramento', $terreno->workflow_stage);
        $this->assertSame('descartado', $terreno->workflow_status_code);
    }

    public function test_viabilidade_aprovada_state_sets_approval_status(): void
    {
        $viabilidade = Viabilidade::factory()->aprovada()->createOne();

        $this->assertSame('aprovada', $viabilidade->approval_status);
        $this->assertNotNull($viabilidade->approval_decided_at);
    }

    public function test_comite_revisao_aprovado_com_ressalvas_state(): void
    {
        $comite = ComiteRevisao::factory()->aprovadoComRessalvas()->createOne();

        $this->assertSame('aprovado_com_ressalvas', $comite->final_decision);
        $this->assertNotNull($comite->decided_at);
    }

    public function test_contrato_assinado_state_sets_signature(): void
    {
        $contrato = Contrato::factory()->assinado()->createOne();

        $this->assertSame('assinado', $contrato->status);
        $this->assertNotNull($contrato->signed_at);
    }

    public function test_task_atrasada_state_sets_past_due_date(): void
    {
        $task = Task::factory()->atrasada()->createOne();

        $this->assertSame('pendente', $task->status);
        $this->assertNull($task->completed_at);
        $this->assertLessThan(now()->toDateString(), $task->due_date->toDateString());
    }

    public function test_proprietario_juridica_state_uses_cnpj(): void
    {
        $proprietario = Proprietario::factory()->juridica()->createOne();

        $this->assertSame('juridica', $proprietario->tipo_pessoa);
        $this->assertNull($proprietario->nascimento);
    }

    public function test_premissas_proprio_state_uses_proprio_profile(): void
    {
        $premissa = PremissasViabilidade::factory()->proprio()->createOne();

        $this->assertSame('proprio', $premissa->perfil_financiamento->value);
    }
}
