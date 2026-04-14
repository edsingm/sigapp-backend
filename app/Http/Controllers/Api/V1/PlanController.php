<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Central\Plan;
use App\Services\ApiResponseService;

class PlanController extends Controller
{
    /**
     * Lista todos os planos ativos.
     *
     * GET /api/v1/plans
     */
    public function index()
    {
        $plans = Plan::active()->ordered()->get();

        return ApiResponseService::success(
            PlanResource::collection($plans),
            language()->t('PLANS_RETRIEVED_SUCCESSFULLY')
        );
    }

    /**
     * Obtém um plano específico pelo slug.
     *
     * GET /api/v1/plans/{slug}
     */
    public function show(string $slug)
    {
        $plan = Plan::where('slug', $slug)->active()->first();

        if (! $plan) {
            return ApiResponseService::notFound(language()->t('PLAN_NOT_FOUND'));
        }

        return ApiResponseService::success(
            new PlanResource($plan),
            language()->t('PLANS_RETRIEVED_SUCCESSFULLY')
        );
    }
}
