<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ApplyContextualAiRequest;
use App\Http\Requests\Tenant\ContextualAiRequest;
use App\Http\Resources\Tenant\AiContextRecommendationResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\ContextualAiService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class ContextualAiController extends Controller
{
    public function __construct(
        private readonly ContextualAiService $service,
    ) {}

    public function context(ContextualAiRequest $request): JsonResponse
    {
        try {
            return ApiResponseService::success(
                new AiContextRecommendationResource($this->service->context($request->user(), $request->validated())),
                'Análise contextual gerada com sucesso',
            );
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('CONTEXTUAL_AI_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function apply(ApplyContextualAiRequest $request, string $recommendation): JsonResponse
    {
        try {
            return ApiResponseService::success(
                new AiContextRecommendationResource($this->service->apply($request->user(), (int) $recommendation, $request->validated())),
                'Recomendação aplicada com sucesso',
            );
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('CONTEXTUAL_AI_APPLY_INVALID', $exception->getMessage(), null, 422);
        }
    }
}
