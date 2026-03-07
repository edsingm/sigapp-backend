<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Central\Plan;
use App\Services\ApiResponseService;

class PlanController extends Controller
{
    /**
     * List all active plans.
     *
     * GET /api/v1/plans
     */
    public function index()
    {
        $plans = Plan::active()->ordered()->get();

        return ApiResponseService::success(
            PlanResource::collection($plans),
            'Planos recuperados com sucesso'
        );
    }

    /**
     * Get a specific plan by slug.
     *
     * GET /api/v1/plans/{slug}
     */
    public function show(string $slug)
    {
        $plan = Plan::where('slug', $slug)->active()->first();

        if (!$plan) {
            return ApiResponseService::notFound('Plano não encontrado');
        }

        return ApiResponseService::success(
            new PlanResource($plan),
            'Plano recuperado com sucesso'
        );
    }
}
