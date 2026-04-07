<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Terreno;
use App\Services\AiScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AiScoringController extends Controller
{
    /**
     * Retorna score de um terreno individual.
     */
    public function getScore(int $terrenoId, AiScoringService $scoringService): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $terreno = Terreno::find($terrenoId);
        if (!$terreno) {
            return new JsonResponse(['message' => 'Terreno não encontrado.'], 404);
        }

        if (Gate::denies('view', $terreno)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $forceRecalculate = request()->boolean('recalculate');
        $result = $scoringService->getScore($terreno, $forceRecalculate);

        return new JsonResponse([
            'data' => [
                'terreno_id' => $terreno->id,
                'terreno_nome' => $terreno->nome,
                ...$result,
            ],
        ]);
    }

    /**
     * Retorna ranking de terrenos por score.
     */
    public function getRanking(AiScoringService $scoringService): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $limit = min(request()->integer('limit', 50), 200);
        $ranking = $scoringService->getRanking($limit);

        return new JsonResponse(['data' => $ranking]);
    }

    /**
     * Recalcula scores de todos os terrenos do tenant.
     */
    public function recalculateAll(AiScoringService $scoringService): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $results = $scoringService->scoreAll();

        return new JsonResponse([
            'data' => [
                'total' => count($results),
                'results' => $results,
            ],
        ]);
    }
}
