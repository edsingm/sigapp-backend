<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\AiInsightGeneratorService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GenerateInsightsTool implements Tool
{
    public function __construct(
        protected AiInsightGeneratorService $insightService
    ) {}

    public function description(): Stringable|string
    {
        return 'Gera insights automáticos do portfólio: taxa de conversão, gargalos de workflow, tendências por cidade e responsável, e concentração de risco.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para gerar insights.';
        }

        $limit = max(1, min((int) ($request['limit'] ?? 10), 50));
        $result = $this->insightService->generateInsights($limit);

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar insights.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer(),
        ];
    }
}
