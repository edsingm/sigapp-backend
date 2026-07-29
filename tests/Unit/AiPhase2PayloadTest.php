<?php

namespace Tests\Unit;

use App\Services\Ai\Agents\SIG_IA;
use App\Services\Ai\Tools\AiPredictivePayload;
use App\Services\Ai\Tools\DetectAnomaliesTool;
use App\Services\Ai\Tools\GetDashboardSummaryTool;
use App\Services\Ai\Tools\GetNegociacaoTool;
use App\Services\Ai\Tools\GetTerrenoDetailsTool;
use App\Services\Ai\Tools\ListTerrenosTool;
use App\Services\Ai\Tools\PredictStallingTool;
use App\Services\Ai\Tools\ProactiveMonitorTool;
use Tests\TestCase;

class AiPhase2PayloadTest extends TestCase
{
    public function test_predictive_payload_adds_disclaimer_and_as_of(): void
    {
        $payload = AiPredictivePayload::withMeta([
            'approval_probability' => 0.72,
            'confidence' => 0.4,
            'benchmarks' => ['total_viabilidades' => 12],
        ]);

        $this->assertSame(12, $payload['sample_size']);
        $this->assertSame('heuristic_historical', $payload['method']);
        $this->assertArrayHasKey('as_of', $payload);
        $this->assertStringContainsString('Não constitui valor contábil', $payload['disclaimer']);
    }

    public function test_tool_descriptions_disambiguate_monitor_vs_stalling_vs_dashboard(): void
    {
        $monitor = (string) (new ProactiveMonitorTool(app(\App\Services\Tenant\LandWorkflowService::class)))->description();
        $stalling = (string) (new PredictStallingTool(app(\App\Services\Ai\Tools\AiPredictiveAnalysisService::class)))->description();
        $dashboard = (string) (new GetDashboardSummaryTool)->description();
        $anomalies = (string) (new DetectAnomaliesTool(app(\App\Services\Ai\Tools\AiAnomalyDetectionService::class)))->description();

        $this->assertStringContainsString('ATUAL', mb_strtoupper($monitor));
        $this->assertStringContainsString('FUTURO', mb_strtoupper($stalling));
        $this->assertTrue(
            str_contains(mb_strtolower($dashboard), 'totais')
            || str_contains(mb_strtolower($dashboard), 'numérico')
            || str_contains(mb_strtolower($dashboard), 'resumo executivo')
        );
        $this->assertStringContainsString('anomalias', mb_strtolower($anomalies));
        $this->assertStringContainsString('ProactiveMonitor', $stalling);
        $this->assertStringContainsString('DetectAnomalies', $monitor);
    }

    public function test_sig_ia_instructions_include_intent_matrix_and_summary_mode(): void
    {
        $instructions = (new SIG_IA)->instructions();

        $this->assertStringContainsString('AnalyzePortfolio', $instructions);
        $this->assertStringContainsString('monitor', $instructions);
        $this->assertStringContainsString('stalling', $instructions);
        $this->assertStringContainsString('SearchPortfolio', $instructions);
        $this->assertStringContainsString('mode=summary', $instructions);
        $this->assertStringContainsString('disclaimer', $instructions);
        $this->assertStringContainsString('viabilidade aprovada', $instructions);
    }

    public function test_details_and_list_schemas_expose_phase2_fields(): void
    {
        $factory = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;

        $detailsSchema = (new GetTerrenoDetailsTool)->schema($factory);
        $listSchema = (new ListTerrenosTool)->schema($factory);
        $negSchema = (new GetNegociacaoTool)->schema($factory);

        $this->assertArrayHasKey('mode', $detailsSchema);
        $this->assertArrayHasKey('include_negociacao', $detailsSchema);
        $this->assertArrayHasKey('cidade', $listSchema);
        $this->assertArrayHasKey('somente_parados', $listSchema);
        $this->assertArrayHasKey('order_by', $listSchema);
        $this->assertArrayHasKey('terreno_id', $negSchema);
    }
}
