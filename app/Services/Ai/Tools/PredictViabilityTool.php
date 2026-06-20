<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\AiPredictiveAnalysisService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PredictViabilityTool implements Tool
{
    public function __construct(
        protected AiPredictiveAnalysisService $predictiveService
    ) {}

    public function description(): Stringable|string
    {
        return 'Prevê a probabilidade de aprovação da viabilidade de um terreno com base em dados históricos de aprovações e terrenos similares.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar previsões.';
        }

        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        if ($terrenoId <= 0) {
            return 'Informe um terreno_id válido.';
        }

        $terreno = Terreno::find($terrenoId);
        if (! $terreno) {
            return "Terreno {$terrenoId} não encontrado.";
        }

        if (Gate::denies('view', $terreno)) {
            return "Acesso negado ao terreno {$terrenoId}.";
        }

        $result = $this->predictiveService->predictApprovalProbability($terreno);

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar previsão.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()->required(),
        ];
    }
}
