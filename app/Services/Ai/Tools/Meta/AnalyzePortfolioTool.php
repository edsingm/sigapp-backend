<?php

namespace App\Services\Ai\Tools\Meta;

use App\Services\Ai\Tools\AiAnomalyDetectionService;
use App\Services\Ai\Tools\AiInsightGeneratorService;
use App\Services\Ai\Tools\AiPredictiveAnalysisService;
use App\Services\Ai\Tools\AnalyticsTool;
use App\Services\Ai\Tools\DetectAnomaliesTool;
use App\Services\Ai\Tools\PredictStallingTool;
use App\Services\Ai\Tools\ProactiveMonitorTool;
use App\Services\Tenant\LandWorkflowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Meta-tool: saúde da carteira (monitor, anomalias, risco futuro, analytics).
 */
class AnalyzePortfolioTool implements Tool
{
    use MetaToolSupport;

    public function description(): Stringable|string
    {
        return 'Análise de carteira. action=monitor (parado AGORA), anomalies (qualidade de dados), '
            .'stalling (risco FUTURO), analytics (insights|trends|compare via type). '
            .'Não misture intents: agora→monitor, futuro→stalling, dados ruins→anomalies, KPIs→SearchPortfolio dashboard.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $this->action($request, 'monitor');
        $forward = $this->forwardRequest($request);

        return match ($action) {
            'monitor' => $this->call(
                new ProactiveMonitorTool(app(LandWorkflowService::class)),
                $forward
            ),
            'anomalies' => $this->call(
                new DetectAnomaliesTool(app(AiAnomalyDetectionService::class)),
                $forward
            ),
            'stalling' => $this->call(
                new PredictStallingTool(app(AiPredictiveAnalysisService::class)),
                $forward
            ),
            'analytics' => $this->call(
                new AnalyticsTool(app(AiInsightGeneratorService::class)),
                $forward
            ),
            default => $this->unknownAction($action, [
                'monitor',
                'anomalies',
                'stalling',
                'analytics',
            ]),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->required()
                ->description('monitor | anomalies | stalling | analytics')
                ->enum(['monitor', 'anomalies', 'stalling', 'analytics']),
            'focus_area' => $schema->string()
                ->description('monitor: stalled | inconsistencies | overdue')
                ->enum(['stalled', 'inconsistencies', 'overdue']),
            'category' => $schema->string()
                ->description('anomalies: categoria opcional')
                ->enum([
                    'workflow_inconsistencies',
                    'financial_anomalies',
                    'duplicate_terrains',
                    'data_quality',
                ]),
            'type' => $schema->string()
                ->description('analytics: insights | trends | compare')
                ->enum(['insights', 'trends', 'compare']),
            'dimension' => $schema->string()
                ->description('analytics trends/compare: city | responsavel | monthly | cidade'),
            'limit' => $schema->integer()->description('máximo de itens')->min(1),
        ];
    }
}
