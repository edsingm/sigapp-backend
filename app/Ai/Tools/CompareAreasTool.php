<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\AiInsightGeneratorService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CompareAreasTool implements Tool
{
    public function __construct(
        protected AiInsightGeneratorService $insightService
    ) {}

    public function description(): Stringable|string
    {
        return 'Compara performance entre responsáveis ou cidades. Retorna ranking baseado em taxa de aprovação, volume e eficiência.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para comparar áreas.';
        }

        $dimension = trim((string) ($request['dimension'] ?? 'responsavel'));
        $limit = max(1, min((int) ($request['limit'] ?? 10), 50));

        $result = $this->insightService->compareAreas($dimension, $limit);

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar comparação.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'dimension' => $schema->string(),
            'limit' => $schema->integer(),
        ];
    }
}
