<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\AiInsightGeneratorService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetTrendsTool implements Tool
{
    public function __construct(
        protected AiInsightGeneratorService $insightService
    ) {}

    public function description(): Stringable|string
    {
        return 'Retorna tendências do portfólio por cidade, responsável ou evolução mensal.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar tendências.';
        }

        $dimension = trim((string) ($request['dimension'] ?? '')) ?: null;
        $result = $this->insightService->getTrends($dimension);

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar tendências.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'dimension' => $schema->string(),
        ];
    }
}
