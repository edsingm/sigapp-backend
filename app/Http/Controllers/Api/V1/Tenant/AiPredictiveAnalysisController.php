<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Terreno;
use App\Repositories\Tenant\TerrenoRepository;
use App\Services\Ai\Tools\AiPredictiveAnalysisService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AiPredictiveAnalysisController extends Controller
{
    public function __construct(
        protected AiPredictiveAnalysisService $predictiveService,
        protected TerrenoRepository $terrenoRepository,
    ) {}

    /**
     * Retorna previsão de aprovação para um terreno.
     */
    public function predictApproval(int $terrenoId): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return ApiResponseService::forbidden('Acesso negado.');
        }

        $terreno = $this->terrenoRepository->findById($terrenoId);
        if (! $terreno) {
            return ApiResponseService::notFound('Terreno não encontrado.');
        }

        if (Gate::denies('view', $terreno)) {
            return ApiResponseService::forbidden('Acesso negado ao terreno.');
        }

        $result = $this->predictiveService->predictApprovalProbability($terreno);

        return ApiResponseService::success($result);
    }

    /**
     * Retorna benchmark de VGV para um terreno.
     */
    public function estimateVgv(int $terrenoId): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return ApiResponseService::forbidden('Acesso negado.');
        }

        $terreno = $this->terrenoRepository->findById($terrenoId);
        if (! $terreno) {
            return ApiResponseService::notFound('Terreno não encontrado.');
        }

        if (Gate::denies('view', $terreno)) {
            return ApiResponseService::forbidden('Acesso negado ao terreno.');
        }

        $result = $this->predictiveService->getVgvBenchmark($terreno);

        return ApiResponseService::success($result);
    }

    /**
     * Retorna previsão de terrenos parados e riscos de stalling.
     */
    public function stallingForecast(): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return ApiResponseService::forbidden('Acesso negado.');
        }

        $result = $this->predictiveService->getStallingForecast();

        return ApiResponseService::success($result);
    }
}
