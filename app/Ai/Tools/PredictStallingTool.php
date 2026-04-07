<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\AiPredictiveAnalysisService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
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
        return 'Prevê terrenos em risco de ficarem parados e identifica os principais gargalos do workflow.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar previsões.';
        }

        $forecast = $this->predictiveService->getStallingForecast();

        return json_encode($forecast, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar previsão.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            // sem parâmetros obrigatórios — retorna análise completa
        ];
    }
}
