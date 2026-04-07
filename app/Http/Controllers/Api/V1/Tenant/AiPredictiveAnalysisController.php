<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Terreno;
use App\Services\AiPredictiveAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AiPredictiveAnalysisController extends Controller
{
    public function __construct(
        protected AiPredictiveAnalysisService $predictiveService
    ) {}

    /**
     * Retorna previsão de aprovação para um terreno.
     */
    public function predictApproval(int $terrenoId): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $terreno = Terreno::find($terrenoId);
        if (! $terreno) {
            return new JsonResponse(['message' => 'Terreno não encontrado.'], 404);
        }

        if (Gate::denies('view', $terreno)) {
            return new JsonResponse(['message' => 'Acesso negado ao terreno.'], 403);
        }

        $result = $this->predictiveService->predictApprovalProbability($terreno);

        return new JsonResponse(['data' => $result]);
    }

    /**
     * Retorna benchmark de VGV para um terreno.
     */
    public function estimateVgv(int $terrenoId): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $terreno = Terreno::find($terrenoId);
        if (! $terreno) {
            return new JsonResponse(['message' => 'Terreno não encontrado.'], 404);
        }

        if (Gate::denies('view', $terreno)) {
            return new JsonResponse(['message' => 'Acesso negado ao terreno.'], 403);
        }

        $result = $this->predictiveService->getVgvBenchmark($terreno);

        return new JsonResponse(['data' => $result]);
    }

    /**
     * Retorna previsão de terrenos parados e riscos de stalling.
     */
    public function stallingForecast(): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $result = $this->predictiveService->getStallingForecast();

        return new JsonResponse(['data' => $result]);
    }
}
