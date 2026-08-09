<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AnalyticsTool implements Tool
{
    public function __construct(
        protected AiInsightGeneratorService $insightService
    ) {}

    public function description(): Stringable|string
    {
        return 'Análise do portfólio em três modos via type: insights (taxa de conversão, gargalos, tendências, risco), trends (tendências por cidade/responsável/mensal), compare (ranking de performance entre responsáveis ou cidades).';
    }

    public function handle(Request $request): Stringable|string
    {
        $auth = app(AiToolAuth::class);
        if ($deny = $auth->ensureViewAny(
            Terreno::class,
            'Acesso negado: você não tem permissão para acessar análises.'
        )) {
            return $deny;
        }

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

        $type = trim((string) ($request['type'] ?? 'insights'));
        $dimension = trim((string) ($request['dimension'] ?? '')) ?: null;
        $limit = AiToolResponse::clampLimit($request['limit'] ?? 10);

        $result = match ($type) {
            'trends' => $this->insightService->getTrends($dimension),
            'compare' => $this->insightService->compareAreas($dimension ?? 'responsavel', $limit),
            default => $this->insightService->generateInsights($limit),
        };

        return AiToolResponse::ok($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->required()
                ->description('Modo: insights | trends | compare')
                ->enum(['insights', 'trends', 'compare']),
            'dimension' => $schema->string()
                ->description('Para trends: city | responsavel | monthly. Para compare: responsavel | cidade.'),
            'limit' => $schema->integer()
                ->description('Máximo de itens quando aplicável (padrão 10).')
                ->min(1),
        ];
    }
}
