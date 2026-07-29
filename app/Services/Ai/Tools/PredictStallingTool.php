<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PredictStallingTool implements Tool
{
    public function __construct(
        protected AiPredictiveAnalysisService $predictiveService
    ) {}

    public function description(): Stringable|string
    {
        return 'Prevê risco FUTURO de travamento no workflow (quais PODEM parar). NÃO use para o que já está parado agora (ProactiveMonitorTool) nem totais da carteira (GetDashboardSummaryTool).';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($deny = app(AiToolAuth::class)->ensureViewAny(
            Terreno::class,
            'Acesso negado: você não tem permissão para acessar previsões.'
        )) {
            return $deny;
        }

        $forecast = $this->predictiveService->getStallingForecast();
        $payload = is_array($forecast) ? $forecast : ['result' => $forecast];

        return AiToolResponse::ok(AiPredictivePayload::withMeta(
            $payload,
            isset($payload['total_active']) ? (int) $payload['total_active'] : null,
            'stalling_forecast_heuristic'
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            // sem parâmetros obrigatórios — retorna análise completa
        ];
    }
}
