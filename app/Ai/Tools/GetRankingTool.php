<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\AiScoringService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetRankingTool implements Tool
{
    public function __construct(
        protected AiScoringService $scoringService
    ) {}

    public function description(): Stringable|string
    {
        return 'Retorna ranking de terrenos ordenado por score de priorização. Use para identificar os terrenos mais promissores.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar ranking.';
        }

        $limit = max(1, min((int) ($request['limit'] ?? 20), 100));
        $ranking = $this->scoringService->getRanking($limit);

        if (empty($ranking)) {
            return 'Ranking indisponível. Execute "php artisan ai:recalculate-scores" primeiro.';
        }

        $payload = [
            'total' => count($ranking),
            'ranking' => $ranking,
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar ranking.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer(),
        ];
    }
}
