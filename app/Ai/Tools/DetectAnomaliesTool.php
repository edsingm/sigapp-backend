<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\AiAnomalyDetectionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
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
        return 'Escaneia o portfólio em busca de anomalias: inconsistências de workflow, VGV desproporcional, terrenos duplicados e problemas de qualidade de dados.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para executar análises.';
        }

        $category = trim((string) ($request['category'] ?? '')) ?: null;
        $limit = max(1, min((int) ($request['limit'] ?? 50), 200));

        $result = $this->anomalyService->scanPortfolio($category, $limit);

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar análise.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string(),
            'limit' => $schema->integer(),
        ];
    }
}
