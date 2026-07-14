<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tenant;

use App\Models\Tenant\AiRequestLog;
use App\Models\Tenant\Terreno;
use App\Services\Ai\Agents\SIG_IA;
use App\Services\Ai\Tools\AiProviderRouter;
use App\Services\Ai\Tools\AiTelemetryService;
use App\Services\Ai\Tools\CompareAreasTool;
use App\Services\Ai\Tools\DetectAnomaliesTool;
use App\Services\Ai\Tools\EstimateVgvTool;
use App\Services\Ai\Tools\GenerateInsightsTool;
use App\Services\Ai\Tools\GetCityIbgeProfileTool;
use App\Services\Ai\Tools\GetComiteTool;
use App\Services\Ai\Tools\GetDashboardSummaryTool;
use App\Services\Ai\Tools\GetDocumentosTool;
use App\Services\Ai\Tools\GetLegalizacaoTool;
use App\Services\Ai\Tools\GetNegociacaoTool;
use App\Services\Ai\Tools\GetRankingTool;
use App\Services\Ai\Tools\GetTasksTool;
use App\Services\Ai\Tools\GetTerrenoDetailsTool;
use App\Services\Ai\Tools\GetTerrenoGeoAnalysisTool;
use App\Services\Ai\Tools\GetTerrenoScoreTool;
use App\Services\Ai\Tools\GetTrendsTool;
use App\Services\Ai\Tools\GetViabilidadesTool;
use App\Services\Ai\Tools\PredictViabilityTool;
use App\Services\Ai\Tools\ProactiveMonitorTool;
use App\Services\Tenant\TerrenoAiNarrativeService;
use App\Services\Tenant\TerrenoAiReportDataService;
use App\Services\Tenant\TerrenoAiReportMapRenderer;
use App\Services\Tenant\TerrenoAiReportService;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class TerrenoAiReportServiceTest extends TestCase
{
    /**
     * @var array<class-string, string>
     */
    private const TOOL_RESULTS = [
        GetTerrenoDetailsTool::class => '{"totais":{"documentos":2,"contatos":1,"proprietarios":1,"viabilidades":1,"projetos":0}}',
        GetTerrenoGeoAnalysisTool::class => '{"area":{"area_util_m2":123.45},"vias":[],"pontos_de_apoio":[]}',
        GetViabilidadesTool::class => '{"items":[{"status":"aprovada"}]}',
        GetLegalizacaoTool::class => '{"items":[]}',
        GetComiteTool::class => '{"items":[]}',
        GetNegociacaoTool::class => '{"items":[]}',
        GetDocumentosTool::class => '{"total":0,"items":[]}',
        GetTasksTool::class => '{"total":0,"items":[]}',
        GetTerrenoScoreTool::class => '{"score":87,"tier":"alto"}',
        GetRankingTool::class => '{"ranking":[{"terreno_id":42}]}',
        PredictViabilityTool::class => '{}',
        EstimateVgvTool::class => '{}',
        GetDashboardSummaryTool::class => '{}',
        ProactiveMonitorTool::class => '{}',
        DetectAnomaliesTool::class => '{}',
        GenerateInsightsTool::class => '{}',
        GetTrendsTool::class => '{}',
        CompareAreasTool::class => '{}',
        GetCityIbgeProfileTool::class => '{}',
    ];

    public function test_build_returns_stable_report_contract_without_map_for_empty_polygon(): void
    {
        $telemetry = $this->mockTelemetry();
        $this->stubTools();

        /** @var SIG_IA&MockInterface $agent */
        $agent = Mockery::mock(SIG_IA::class);
        $agent->shouldReceive('prompt')
            ->once()
            ->andReturn(new AgentResponse(
                'invocation-1',
                '**Resumo Executivo**\nRelatório gerado com dados estruturados.',
                new Usage(10, 5, 0, 2),
                new Meta('fake-provider', 'fake-model'),
            ));
        $this->stubProviderRouter($agent);

        $service = new TerrenoAiReportService(
            new TerrenoAiReportDataService(new TerrenoAiReportMapRenderer),
            new TerrenoAiNarrativeService($telemetry),
        );
        $result = $service->build($this->makeTerreno());

        $this->assertSame('Relatório SIG IA do Terreno #42', $result['title']);
        $this->assertSame('relatorio-sig-ia-terreno-42-terreno-de-teste', $result['filename']);
        $this->assertStringContainsString('Resumo Executivo', $result['html_content']);
        $this->assertStringNotContainsString('Mapa do Polígono', $result['html_content']);
        $this->assertArrayHasKey('html_content', $result);
    }

    public function test_narrative_service_records_successful_provider_usage(): void
    {
        $telemetry = $this->mockTelemetry();
        /** @var SIG_IA&MockInterface $agent */
        $agent = Mockery::mock(SIG_IA::class);
        $agent->shouldReceive('prompt')->once()->andReturn(new AgentResponse(
            'invocation-2',
            '**Resumo Executivo**\nNarrativa gerada.',
            new Usage(10, 5, 0, 2),
            new Meta('fake-provider', 'fake-model'),
        ));
        $this->stubProviderRouter($agent);

        $result = (new TerrenoAiNarrativeService($telemetry))->generate($this->context());

        $this->assertSame('**Resumo Executivo**\nNarrativa gerada.', $result['markdown']);
        $this->assertStringContainsString('<strong>Resumo Executivo</strong>', $result['html']);
        $telemetry->shouldHaveReceived('ensureBudgetAvailable');
        $telemetry->shouldHaveReceived('estimateCost', ['fake-provider', 'fake-model', 10, 5, 2]);
        $telemetry->shouldHaveReceived('logRequest', [Mockery::on(
            static fn (array $data): bool => ($data['status'] ?? null) === 'success'
                && ($data['prompt_tokens'] ?? null) === 10
                && ($data['completion_tokens'] ?? null) === 5,
        )]);
    }

    public function test_narrative_service_uses_fallback_when_provider_fails_and_logs_error(): void
    {
        $telemetry = $this->mockTelemetry(false);
        /** @var SIG_IA&MockInterface $agent */
        $agent = Mockery::mock(SIG_IA::class);
        $agent->shouldReceive('prompt')->once()->andThrow(new RuntimeException('provider indisponível'));
        $this->stubProviderRouter($agent);

        $result = (new TerrenoAiNarrativeService($telemetry))->generate($this->context());

        $this->assertStringContainsString('A IA não conseguiu redigir a narrativa completa', $result['markdown']);
        $this->assertStringContainsString('provider indisponível', $result['markdown']);
        $this->assertStringContainsString('Terreno de Teste', $result['html']);
        $telemetry->shouldHaveReceived('ensureBudgetAvailable');
        $telemetry->shouldHaveReceived('logRequest', [Mockery::on(
            static fn (array $data): bool => ($data['status'] ?? null) === 'error'
                && ($data['error_message'] ?? null) === 'provider indisponível',
        )]);
        $telemetry->shouldNotHaveReceived('estimateCost');
    }

    public function test_narrative_service_falls_back_without_prompting_when_budget_is_unavailable(): void
    {
        /** @var AiTelemetryService&MockInterface $telemetry */
        $telemetry = Mockery::mock(AiTelemetryService::class);
        $telemetry->shouldReceive('ensureBudgetAvailable')
            ->once()
            ->andThrow(new RuntimeException('orçamento excedido'));
        $telemetry->shouldReceive('logRequest')->once()->andReturn(new AiRequestLog);

        /** @var SIG_IA&MockInterface $agent */
        $agent = Mockery::mock(SIG_IA::class);
        $agent->shouldNotReceive('prompt');
        $this->stubProviderRouter($agent);

        $result = (new TerrenoAiNarrativeService($telemetry))->generate($this->context());

        $this->assertStringContainsString('orçamento excedido', $result['markdown']);
    }

    public function test_map_renderer_discards_invalid_points_and_returns_empty_map_without_polygon(): void
    {
        $renderer = new TerrenoAiReportMapRenderer;
        $result = $renderer->prepare([
            ['lat' => '-23.5', 'lng' => '-46.6'],
            ['lat' => 1],
            'invalid',
            ['lat' => -23.6, 'lng' => -46.7],
        ], []);

        $this->assertSame([
            ['lat' => -23.5, 'lng' => -46.6],
            ['lat' => -23.6, 'lng' => -46.7],
        ], $result['polygon']);

        $emptyResult = $renderer->prepare(null, []);
        $this->assertSame([], $emptyResult['polygon']);
        $this->assertSame('', $emptyResult['map_data_uri']);
    }

    private function mockTelemetry(bool $withCost = true): AiTelemetryService&MockInterface
    {
        $telemetry = Mockery::mock(AiTelemetryService::class);
        $telemetry->shouldReceive('ensureBudgetAvailable')->once();
        if ($withCost) {
            $telemetry->shouldReceive('estimateCost')->once()->andReturn(0.123);
        }
        $telemetry->shouldReceive('logRequest')->once()->andReturn(new AiRequestLog);

        if (! $telemetry instanceof AiTelemetryService) {
            throw new RuntimeException('Mock de telemetria inválido.');
        }

        return $telemetry;
    }

    private function stubProviderRouter(SIG_IA&MockInterface $agent): void
    {
        /** @var AiProviderRouter&MockInterface $router */
        $router = Mockery::mock(AiProviderRouter::class);
        $router->shouldReceive('getAgentWithFallback')->once()->andReturn([
            'agent' => $agent,
            'provider' => 'fake-provider',
            'model' => 'fake-model',
            'providers' => ['fake-provider' => 'fake-model'],
            'isFallback' => false,
        ]);
        $this->app->instance(AiProviderRouter::class, $router);
    }

    private function stubTools(): void
    {
        foreach (self::TOOL_RESULTS as $toolClass => $result) {
            $tool = Mockery::mock($toolClass);
            $tool->shouldReceive('handle')->andReturn($result);
            $this->app->instance($toolClass, $tool);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'terreno' => [
                'id' => 42,
                'nome' => 'Terreno de Teste',
                'cidade' => 'São Paulo',
                'estado' => 'SP',
            ],
            'workflowSummary' => [
                ['label' => 'Status', 'value' => 'Em análise'],
            ],
            'geoSummary' => ['area_util_m2' => 123.45],
            'marketSummary' => ['score' => ['score' => 87, 'tier' => 'alto']],
            'supportPoints' => [],
        ];
    }

    private function makeTerreno(): Terreno
    {
        $terreno = new Terreno;
        $terreno->id = 42;
        $terreno->nome = 'Terreno de Teste';
        $terreno->estado = 'SP';
        $terreno->workflow_status_code = 'em_analise';
        $terreno->workflow_status_label = 'Em análise';
        $terreno->workflow_stage = 'captacao';
        $terreno->polygon_coords = null;
        $terreno->updated_at = now();

        return $terreno;
    }
}
