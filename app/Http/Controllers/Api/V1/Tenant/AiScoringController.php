<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\RecalculateAiScoresJob;
use App\Models\Tenant\Terreno;
use App\Repositories\Tenant\TerrenoRepository;
use App\Services\AiScoringService;
use App\Services\ApiResponseService;
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
            return ApiResponseService::forbidden('Acesso negado.');
        }

        $terreno = $this->terrenoRepository->findById($terrenoId);
        if (! $terreno) {
            return ApiResponseService::notFound('Terreno não encontrado.');
        }

        if (Gate::denies('view', $terreno)) {
            return ApiResponseService::forbidden('Acesso negado.');
        }

        $result = $this->scoringService->getScore($terreno);

        return ApiResponseService::success([
            'terreno_id' => $terreno->getKey(),
            'terreno_nome' => (string) $terreno->getAttribute('nome'),
            ...$result,
        ]);
    }

    /**
     * Retorna ranking de terrenos por score.
     */
    public function getRanking(): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return ApiResponseService::forbidden('Acesso negado.');
        }

        $limit = min(request()->integer('limit', 50), 200);
        $ranking = $this->scoringService->getRanking($limit);

        return ApiResponseService::success($ranking);
    }

    /**
     * Enfileira recálculo de scores de todos os terrenos do tenant.
     */
    public function recalculateAll(): JsonResponse
    {
        if (Gate::denies('update', Terreno::class)) {
            return ApiResponseService::forbidden('Acesso negado.');
        }

        RecalculateAiScoresJob::dispatch();

        return ApiResponseService::success(null, 'Recálculo de scores enfileirado.', 202);
    }
}
