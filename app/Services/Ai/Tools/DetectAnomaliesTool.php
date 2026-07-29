<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DetectAnomaliesTool implements Tool
{
    public function __construct(
        protected AiAnomalyDetectionService $anomalyService
    ) {}

    public function description(): Stringable|string
    {
        return 'Escaneia anomalias de DADOS/CADASTRO: inconsistências de workflow, VGV desproporcional, duplicados e qualidade. NÃO use para parados atuais (ProactiveMonitor) nem previsão de travamento (PredictStalling) nem KPIs (Dashboard).';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($deny = app(AiToolAuth::class)->ensureViewAny(
            Terreno::class,
            'Acesso negado: você não tem permissão para executar análises.'
        )) {
            return $deny;
        }

        $category = trim((string) ($request['category'] ?? '')) ?: null;
        $limit = AiToolResponse::clampLimit($request['limit'] ?? 50, default: 50, max: 50);

        $result = $this->anomalyService->scanPortfolio($category, $limit);

        return AiToolResponse::ok(is_array($result) ? $result : ['result' => $result]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()
                ->description('Categoria opcional de anomalia.')
                ->enum([
                    'workflow_inconsistencies',
                    'financial_anomalies',
                    'duplicate_terrains',
                    'data_quality',
                ]),
            'limit' => $schema->integer()
                ->description('Máximo de itens (padrão 50).')
                ->min(1),
        ];
    }
}
