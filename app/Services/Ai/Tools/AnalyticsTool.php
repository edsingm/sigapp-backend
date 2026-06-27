<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
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
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar análises.';
        }

        $type = trim((string) ($request['type'] ?? 'insights'));
        $dimension = trim((string) ($request['dimension'] ?? '')) ?: null;
        $limit = max(1, min((int) ($request['limit'] ?? 10), 50));

        $result = match ($type) {
            'trends' => $this->insightService->getTrends($dimension),
            'compare' => $this->insightService->compareAreas($dimension ?? 'responsavel', $limit),
            default => $this->insightService->generateInsights($limit),
        };

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar análise.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->required()->description('insights | trends | compare'),
            'dimension' => $schema->string()->description('Para trends: city | responsavel | monthly. Para compare: responsavel | cidade.'),
            'limit' => $schema->integer(),
        ];
    }
}
