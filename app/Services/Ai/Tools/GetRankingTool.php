<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
        if ($deny = app(AiToolAuth::class)->ensureViewAny(
            Terreno::class,
            'Acesso negado: você não tem permissão para acessar ranking.'
        )) {
            return $deny;
        }

        $limit = AiToolResponse::clampLimit($request['limit'] ?? 20, default: 20, max: 50);
        $ranking = $this->scoringService->getRanking($limit);

        if (empty($ranking)) {
            return AiToolResponse::empty('Ranking indisponível. Execute "php artisan ai:recalculate-scores" primeiro.');
        }

        return AiToolResponse::ok([
            'ranking' => $ranking,
            'meta' => AiToolResponse::listMeta(count($ranking), count($ranking), $limit),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Quantidade de terrenos no ranking (padrão 20).')->min(1),
        ];
    }
}
