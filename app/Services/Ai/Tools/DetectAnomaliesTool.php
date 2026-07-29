<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Contrato;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
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
        $auth = app(AiToolAuth::class);
        if ($deny = $auth->ensureViewAny(
            Terreno::class,
            'Acesso negado: você não tem permissão para executar análises.'
        )) {
            return $deny;
        }

        $category = trim((string) ($request['category'] ?? '')) ?: null;
        $limit = AiToolResponse::clampLimit($request['limit'] ?? 50, default: 50, max: 50);

        if ($category === null || in_array($category, ['workflow_inconsistencies', 'financial_anomalies'], true)) {
            if ($deny = $auth->ensureFeature(
                'viabilities.enabled',
                'Acesso negado: seu plano não inclui viabilidades.'
            )) {
                return $deny;
            }
            if ($deny = $auth->ensureViewAny(
                Viabilidade::class,
                'Acesso negado: você não tem permissão para acessar viabilidades.'
            )) {
                return $deny;
            }
        }

        if ($category === null || $category === 'workflow_inconsistencies') {
            foreach ([
                ['committee', ComiteRevisao::class, 'comitês'],
                ['negotiation', Contrato::class, 'contratos'],
                ['legalizations', Legalizacao::class, 'legalizações'],
            ] as [$feature, $model, $label]) {
                if ($deny = $auth->ensureFeature(
                    $feature,
                    "Acesso negado: seu plano não inclui {$label}."
                )) {
                    return $deny;
                }
                if ($deny = $auth->ensureViewAny(
                    $model,
                    "Acesso negado: você não tem permissão para acessar {$label}."
                )) {
                    return $deny;
                }
            }
        }

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
