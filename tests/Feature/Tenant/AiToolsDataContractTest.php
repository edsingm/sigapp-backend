<?php

namespace Tests\Feature\Tenant;

use App\Models\Central\Tenant;
use App\Models\Tenant\ComiteParecerDepartamento;
use App\Models\Tenant\ComitePendencia;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\LegalizacaoPendencia;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\NegociacaoEvento;
use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use App\Services\Ai\Tools\GetComiteTool;
use App\Services\Ai\Tools\GetLegalizacaoTool;
use App\Services\Ai\Tools\GetNegociacaoTool;
use App\Services\Ai\Tools\GetTasksTool;
use App\Services\PlanMatrixService;
use Database\Factories\Tenant\TerrenoFactory;
use Database\Factories\Tenant\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Tools\Request;
use Mockery;
use Stancl\Tenancy\Tenancy;
use Tests\TestCase;

class AiToolsDataContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Terreno $terreno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
        $this->user = UserFactory::new()->createOne();
        $this->terreno = TerrenoFactory::new()->createOne(['created_by' => $this->user->id]);

        app(Tenancy::class)->tenant = new Tenant;
        $this->app->instance(PlanMatrixService::class, Mockery::mock(PlanMatrixService::class, function ($mock): void {
            $mock->shouldReceive('hasFeatureForTenant')->andReturnTrue();
        }));
        Gate::before(static fn (): bool => true);
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        app(Tenancy::class)->tenant = null;
        Mockery::close();

        parent::tearDown();
    }

    public function test_tools_expose_current_legalization_committee_negotiation_and_task_fields(): void
    {
        $legalizacao = Legalizacao::create([
            'terreno_id' => $this->terreno->id,
            'nome' => 'Legalização do terreno',
            'status' => 'em_andamento',
            'created_by' => $this->user->id,
        ]);
        LegalizacaoEtapa::create([
            'legalizacao_id' => $legalizacao->id,
            'titulo' => 'Aprovação municipal',
            'status' => 'em_andamento',
            'inicio_planejado' => now()->subDays(2),
            'fim_planejado' => now()->subDay(),
            'valor_custo' => 1000,
            'custo_pago' => true,
            'ordem' => 1,
        ]);
        LegalizacaoPendencia::create([
            'legalizacao_id' => $legalizacao->id,
            'title' => 'Anexar certidão',
            'severity' => 'high',
            'status' => 'aberta',
        ]);

        $viabilidade = Viabilidade::create([
            'terreno_id' => $this->terreno->id,
            'status' => 'em_analise',
            'created_by' => $this->user->id,
        ]);
        $review = ComiteRevisao::create([
            'terreno_id' => $this->terreno->id,
            'viabilidade_id' => $viabilidade->id,
            'status' => 'em_andamento',
        ]);
        ComiteParecerDepartamento::create([
            'comite_revisao_id' => $review->id,
            'department_code' => 'juridico',
            'decision' => 'aprovado_com_ressalvas',
            'comments' => 'Validar matrícula atualizada.',
        ]);
        ComitePendencia::create([
            'comite_revisao_id' => $review->id,
            'terreno_id' => $this->terreno->id,
            'title' => 'Matrícula atualizada',
            'description' => 'Solicitar documento ao proprietário.',
            'severity' => 'high',
            'status' => 'aberta',
            'department_code' => 'juridico',
            'responsible_user_id' => $this->user->id,
        ]);

        $negociacao = Negociacao::create([
            'terreno_id' => $this->terreno->id,
            'status' => 'em_andamento',
            'started_at' => now()->subDays(2),
        ]);
        NegociacaoEvento::create([
            'negociacao_id' => $negociacao->id,
            'event_type' => 'offer.sent',
            'notes' => 'Proposta enviada ao proprietário.',
            'payload_json' => ['amount' => 500000],
            'happened_at' => now()->subDay(),
        ]);

        Task::create([
            'terreno_id' => $this->terreno->id,
            'title' => 'Revisar proposta',
            'status' => 'open',
            'due_date' => now()->subDay(),
            'created_by' => $this->user->id,
        ]);

        $legalizacaoPayload = json_decode((string) app(GetLegalizacaoTool::class)->handle(new Request([
            'terreno_id' => $this->terreno->id,
            'include_etapas' => true,
        ])), true);
        $comitePayload = json_decode((string) app(GetComiteTool::class)->handle(new Request(['terreno_id' => $this->terreno->id])), true);
        $negociacaoPayload = json_decode((string) app(GetNegociacaoTool::class)->handle(new Request(['terreno_id' => $this->terreno->id])), true);
        $tasksPayload = json_decode((string) app(GetTasksTool::class)->handle(new Request(['terreno_id' => $this->terreno->id])), true);

        $this->assertTrue($legalizacaoPayload['ok'] ?? false);
        $this->assertSame('OK', $legalizacaoPayload['code'] ?? null);

        $this->assertSame('Aprovação municipal', $legalizacaoPayload['data']['items'][0]['etapas'][0]['nome']);
        $this->assertSame(1000, $legalizacaoPayload['data']['items'][0]['etapas'][0]['custo_realizado']);
        $this->assertSame('Anexar certidão', $legalizacaoPayload['data']['items'][0]['pendencias'][0]['descricao']);
        $this->assertSame('juridico', $comitePayload['data']['items'][0]['pareceres'][0]['departamento']);
        $this->assertSame('aprovado_com_ressalvas', $comitePayload['data']['items'][0]['pareceres'][0]['posicao']);
        $this->assertSame('offer.sent', $negociacaoPayload['data']['items'][0]['eventos'][0]['tipo']);
        $this->assertSame('Proposta enviada ao proprietário.', $negociacaoPayload['data']['items'][0]['eventos'][0]['descricao']);
        $this->assertArrayNotHasKey('dados', $negociacaoPayload['data']['items'][0]['eventos'][0]);
        $this->assertSame(1, $tasksPayload['data']['resumo']['overdue']);
    }
}
