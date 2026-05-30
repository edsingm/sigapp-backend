<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Terreno;
use App\Repositories\Tenant\TerrenoRepository;
use App\Services\AiScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AiScoringController extends Controller
{
    public function __construct(
        protected AiScoringService $scoringService,
        protected TerrenoRepository $terrenoRepository,
    ) {}

    /**
     * Retorna score de um terreno individual.
     */
    public function getScore(int $terrenoId): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $terreno = $this->terrenoRepository->findById($terrenoId);
        if (!$terreno) {
            return new JsonResponse(['message' => 'Terreno não encontrado.'], 404);
        }

        if (Gate::denies('view', $terreno)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $forceRecalculate = request()->boolean('recalculate');
        $result = $this->scoringService->getScore($terreno, $forceRecalculate);

        return new JsonResponse([
            'data' => [
                'terreno_id' => $terreno->getKey(),
                'terreno_nome' => (string) $terreno->getAttribute('nome'),
                ...$result,
            ],
        ]);
    }

    /**
     * Retorna ranking de terrenos por score.
     */
    public function getRanking(): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $limit = min(request()->integer('limit', 50), 200);
        $ranking = $this->scoringService->getRanking($limit);

        return new JsonResponse(['data' => $ranking]);
    }

    /**
     * Recalcula scores de todos os terrenos do tenant.
     */
    public function recalculateAll(): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $results = $this->scoringService->scoreAll();

        return new JsonResponse([
            'data' => [
                'total' => count($results),
                'results' => $results,
            ],
        ]);
    }
}
