<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function __construct(
        private readonly PlanRepositoryInterface $planRepository,
    ) {}

    public function index(): JsonResponse
    {
        $plans = $this->planRepository->findAllActiveOrdered();

        return ApiResponseService::success(
            PlanResource::collection($plans),
            language()->t('PLANS_RETRIEVED_SUCCESSFULLY')
        );
    }

    public function show(string $slug): JsonResponse
    {
        $plan = $this->planRepository->findActiveBySlug($slug);

        if (! $plan) {
            return ApiResponseService::notFound(language()->t('PLAN_NOT_FOUND'));
        }

        return ApiResponseService::success(
            new PlanResource($plan),
            language()->t('PLANS_RETRIEVED_SUCCESSFULLY')
        );
    }
}
