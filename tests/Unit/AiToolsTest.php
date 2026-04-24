<?php

namespace Tests\Unit;

use App\Ai\Agents\SIG_IA;
use App\Ai\Tools\AnalyzeDocumentTool;
use App\Ai\Tools\CompareAreasTool;
use App\Ai\Tools\CreatePdfsTool;
use App\Ai\Tools\CreateTaskTool;
use App\Ai\Tools\DetectAnomaliesTool;
use App\Ai\Tools\EstimateVgvTool;
use App\Ai\Tools\GenerateInsightsTool;
use App\Ai\Tools\GetComiteTool;
use App\Ai\Tools\GetDashboardSummaryTool;
use App\Ai\Tools\GetDocumentosTool;
use App\Ai\Tools\GetLegalizacaoTool;
use App\Ai\Tools\GetNegociacaoTool;
use App\Ai\Tools\GetRankingTool;
use App\Ai\Tools\GetTasksTool;
use App\Ai\Tools\GetTerrenoDetailsTool;
use App\Ai\Tools\GetTerrenoScoreTool;
use App\Ai\Tools\GetTrendsTool;
use App\Ai\Tools\GetViabilidadesTool;
use App\Ai\Tools\ListTerrenosTool;
use App\Ai\Tools\PredictStallingTool;
use App\Ai\Tools\PredictViabilityTool;
use App\Ai\Tools\ProactiveMonitorTool;
use App\Ai\Tools\SearchDocumentsTool;
use App\Ai\Tools\TransitionWorkflowTool;
use App\Ai\Tools\UpdateTaskStatusTool;
use App\Services\AiAnomalyDetectionService;
use App\Services\AiInsightGeneratorService;
use App\Services\AiPredictiveAnalysisService;
use App\Services\AiScoringService;
use App\Services\Tenant\LandWorkflowService;
use Laravel\Ai\Contracts\Tool;
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
            GetViabilidadesTool::class,
            GetLegalizacaoTool::class,
            GetComiteTool::class,
            GetNegociacaoTool::class,
            GetDocumentosTool::class,
            GetDashboardSummaryTool::class,
            GetTasksTool::class,
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
        $tool = new GetViabilidadesTool;

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_terreno_details_tool_has_description(): void
    {
        $tool = new GetTerrenoDetailsTool;

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_legalizacao_tool_has_description(): void
    {
        $tool = new GetLegalizacaoTool;

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_comite_tool_has_description(): void
    {
        $tool = new GetComiteTool;

        $this->assertNotEmpty($tool->description());
    }

    public function test_get_negociacao_tool_has_description(): void
    {
        $tool = new GetNegociacaoTool;

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

    public function test_sig_ia_uses_openrouter_provider(): void
    {
        $agent = new SIG_IA;

        $this->assertEquals('openrouter', $agent->provider());
    }

    public function test_sig_ia_has_reasoning_enabled(): void
    {
        $agent = new SIG_IA;
        $options = $agent->providerOptions('openrouter');

        $this->assertSame(true, $options['reasoning']['enabled'] ?? false);
        $this->assertSame(true, $options['reasoning']['exclude'] ?? false);
    }
}
