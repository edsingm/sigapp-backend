<?php

namespace Tests\Unit;

use App\Services\Ai\Agents\SIG_IA;
use App\Services\Ai\Tools\AnalyzeDocumentTool;
use App\Services\Ai\Tools\CompareAreasTool;
use App\Services\Ai\Tools\CreatePdfsTool;
use App\Services\Ai\Tools\CreateTaskTool;
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
use App\Services\Ai\Tools\ListTerrenosTool;
use App\Services\Ai\Tools\PredictStallingTool;
use App\Services\Ai\Tools\PredictViabilityTool;
use App\Services\Ai\Tools\ProactiveMonitorTool;
use App\Services\Ai\Tools\SearchDocumentsTool;
use App\Services\Ai\Tools\TransitionWorkflowTool;
use App\Services\Ai\Tools\UpdateTaskStatusTool;
use App\Services\Ai\Tools\AiAnomalyDetectionService;
use App\Services\Ai\Tools\AiIbgeCityProfileService;
use App\Services\Ai\Tools\AiInsightGeneratorService;
use App\Services\Ai\Tools\AiPredictiveAnalysisService;
use App\Services\Ai\Tools\AiScoringService;
use App\Services\Tenant\Area\PolygonCalculator;
use App\Services\Tenant\Geo\GeoProximityService;
use App\Services\Tenant\LandWorkflowService;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

/**
 * Testa o catálogo de tools do SIG_IA.
 *
 * Valida existência, registro no agent e schema.
 * Os testes de execução/Gate requerem tenancy + database e são feature tests.
 */
class AiToolsTest extends TestCase
{
    // ── Registro no Agent ─────────────────────────────────────────

    public function test_sig_ai_registers_all_tools(): void
    {
        $agent = new SIG_IA;
        $tools = collect($agent->tools());

        $expected = [
            ListTerrenosTool::class,
            GetTerrenoDetailsTool::class,
            GetTerrenoGeoAnalysisTool::class,
            GetViabilidadesTool::class,
            GetLegalizacaoTool::class,
            GetComiteTool::class,
            GetNegociacaoTool::class,
            GetDocumentosTool::class,
            GetDashboardSummaryTool::class,
            GetTasksTool::class,
            GetCityIbgeProfileTool::class,
            SearchDocumentsTool::class,
            AnalyzeDocumentTool::class,
            GetTerrenoScoreTool::class,
            GetRankingTool::class,
            CreateTaskTool::class,
            UpdateTaskStatusTool::class,
            TransitionWorkflowTool::class,
            ProactiveMonitorTool::class,
            PredictViabilityTool::class,
            EstimateVgvTool::class,
            PredictStallingTool::class,
            DetectAnomaliesTool::class,
            GenerateInsightsTool::class,
            GetTrendsTool::class,
            CompareAreasTool::class,
            CreatePdfsTool::class,
        ];

        $actual = $tools->map(fn ($t) => $t::class)->sort()->values();
        sort($expected);

        $this->assertEquals($expected, $actual->toArray());
    }

    public function test_all_tools_implement_tool_contract(): void
    {
        $agent = new SIG_IA;

        foreach ($agent->tools() as $tool) {
            $this->assertInstanceOf(
                Tool::class,
                $tool,
                get_class($tool).' must implement Laravel\Ai\Contracts\Tool'
            );
        }
    }

    // ── Tool: descrição e schema ──────────────────────────────────

    public function test_list_terrenos_tool_has_description(): void
    {
        $tool = new ListTerrenosTool;

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_viabilidades_tool_has_description(): void
    {
        $tool = app(GetViabilidadesTool::class);

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_terreno_details_tool_has_description(): void
    {
        $tool = new GetTerrenoDetailsTool;

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_terreno_geo_analysis_tool_has_description(): void
    {
        $tool = new GetTerrenoGeoAnalysisTool(
            app(GeoProximityService::class),
            app(PolygonCalculator::class),
        );

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_terreno_geo_analysis_tool_returns_error_without_id(): void
    {
        $tool = new GetTerrenoGeoAnalysisTool(
            app(GeoProximityService::class),
            app(PolygonCalculator::class),
        );

        $result = $tool->handle(new Request([]));

        $this->assertStringContainsString('terreno_id', (string) $result);
    }

    public function test_get_legalizacao_tool_has_description(): void
    {
        $tool = app(GetLegalizacaoTool::class);

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_comite_tool_has_description(): void
    {
        $tool = app(GetComiteTool::class);

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_negociacao_tool_has_description(): void
    {
        $tool = app(GetNegociacaoTool::class);

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_documentos_tool_has_description(): void
    {
        $tool = new GetDocumentosTool;

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_dashboard_summary_tool_has_description(): void
    {
        $tool = new GetDashboardSummaryTool;

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_tasks_tool_has_description(): void
    {
        $tool = new GetTasksTool;

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_city_ibge_profile_tool_has_description(): void
    {
        $tool = new GetCityIbgeProfileTool(
            app(AiIbgeCityProfileService::class)
        );

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_terreno_score_tool_has_description(): void
    {
        $tool = new GetTerrenoScoreTool(
            app(AiScoringService::class)
        );

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_ranking_tool_has_description(): void
    {
        $tool = new GetRankingTool(
            app(AiScoringService::class)
        );

        $this->assertNotEmpty($tool->description());
    }

    public function test_create_task_tool_has_description(): void
    {
        $tool = new CreateTaskTool;

        $this->assertNotEmpty($tool->description());
    }

    public function test_update_task_status_tool_has_description(): void
    {
        $tool = new UpdateTaskStatusTool;

        $this->assertNotEmpty($tool->description());
    }

    public function test_transition_workflow_tool_has_description(): void
    {
        $tool = new TransitionWorkflowTool(
            app(LandWorkflowService::class)
        );

        $this->assertNotEmpty($tool->description());
    }

    public function test_proactive_monitor_tool_has_description(): void
    {
        $tool = new ProactiveMonitorTool(
            app(LandWorkflowService::class)
        );

        $this->assertNotEmpty($tool->description());
    }

    public function test_predict_viability_tool_has_description(): void
    {
        $tool = new PredictViabilityTool(
            app(AiPredictiveAnalysisService::class)
        );

        $this->assertNotEmpty($tool->description());
    }

    public function test_estimate_vgv_tool_has_description(): void
    {
        $tool = new EstimateVgvTool(
            app(AiPredictiveAnalysisService::class)
        );

        $this->assertNotEmpty($tool->description());
    }

    public function test_predict_stalling_tool_has_description(): void
    {
        $tool = new PredictStallingTool(
            app(AiPredictiveAnalysisService::class)
        );

        $this->assertNotEmpty($tool->description());
    }

    public function test_detect_anomalies_tool_has_description(): void
    {
        $tool = new DetectAnomaliesTool(
            app(AiAnomalyDetectionService::class)
        );

        $this->assertNotEmpty($tool->description());
    }

    public function test_generate_insights_tool_has_description(): void
    {
        $tool = new GenerateInsightsTool(
            app(AiInsightGeneratorService::class)
        );

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_trends_tool_has_description(): void
    {
        $tool = new GetTrendsTool(
            app(AiInsightGeneratorService::class)
        );

        $this->assertNotEmpty($tool->description());
    }

    public function test_compare_areas_tool_has_description(): void
    {
        $tool = new CompareAreasTool(
            app(AiInsightGeneratorService::class)
        );

        $this->assertNotEmpty($tool->description());
    }

    public function test_create_pdfs_tool_returns_friendly_message_when_chrome_is_missing(): void
    {
        Pdf::shouldReceive('view')
            ->once()
            ->andThrow(new RuntimeException('Could not find Chrome (ver. 148.0.7778.97).'));

        $tool = new CreatePdfsTool;

        $result = $tool->handle(new Request([
            'filename' => 'relatorio-ibge',
            'title' => 'Relatorio IBGE',
            'html_content' => '<html><body><h1>Teste</h1></body></html>',
        ]));

        $this->assertStringContainsString('Chrome/Chromium', $result);
        $this->assertStringContainsString('infraestrutura de PDF', $result);
    }

    public function test_sig_ia_uses_configured_provider(): void
    {
        $agent = new SIG_IA;

        $this->assertEquals(
            (string) config('ai.agent_provider', 'openrouter'),
            $agent->provider()
        );
    }

    public function test_sig_ia_has_reasoning_enabled(): void
    {
        $agent = new SIG_IA;
        $options = $agent->providerOptions('openrouter');

        $this->assertSame(true, $options['reasoning']['enabled'] ?? false);
        $this->assertSame(true, $options['reasoning']['exclude'] ?? false);
    }
}
