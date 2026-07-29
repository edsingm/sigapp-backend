<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
        return 'Estima (heurística) a probabilidade de aprovação da viabilidade com base no histórico do tenant. NÃO é parecer formal. Não use para alertas de parada atual (ProactiveMonitor) nem anomalias de cadastro (DetectAnomalies).';
    }

    public function handle(Request $request): Stringable|string
    {
        $auth = app(AiToolAuth::class);
        if ($deny = $auth->ensureViewAny(
            Terreno::class,
            'Acesso negado: você não tem permissão para acessar previsões.'
        )) {
            return $deny;
        }

        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        if ($terrenoId <= 0) {
            return AiToolResponse::validation('Informe um terreno_id válido.');
        }

        $terreno = Terreno::find($terrenoId);
        if (! $terreno) {
            return AiToolResponse::empty("Terreno {$terrenoId} não encontrado.");
        }

        if ($deny = $auth->ensureView($terreno, "Acesso negado ao terreno {$terrenoId}.")) {
            return $deny;
        }

        $result = $this->predictiveService->predictApprovalProbability($terreno);
        $payload = is_array($result) ? $result : ['result' => $result];

        return AiToolResponse::ok(AiPredictivePayload::withMeta($payload));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()->required()->description('ID do terreno para prever aprovação.'),
        ];
    }
}
