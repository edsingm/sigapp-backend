<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\SyncPlanEntitlementsRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Http\Resources\AdminPlanResource;
use App\Services\ApiResponseService;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class PlanAdminController extends Controller
{
    public function __construct(
        private readonly PlanService $planService
    ) {}

    public function index(): JsonResponse
    {
        $plans = $this->planService->list();

        return ApiResponseService::success(
            AdminPlanResource::collection($plans),
            'DATA_RETRIEVED_SUCCESSFULLY'
        );
    }

    public function show(int $plan): JsonResponse
    {
        try {
            $model = $this->planService->findOrFail($plan);
            $model->load('entitlements');
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::notFound(language()->t('PLAN_NOT_FOUND'));
        }

        return ApiResponseService::success(new AdminPlanResource($model));
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $model = $this->planService->create($request->validated());

        return ApiResponseService::created(new AdminPlanResource($model));
    }

    public function update(UpdatePlanRequest $request, int $plan): JsonResponse
    {
        try {
            $model = $this->planService->update($plan, $request->validated());
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::notFound(language()->t('PLAN_NOT_FOUND'));
        }

        return ApiResponseService::success(
            new AdminPlanResource($model),
            'SUCCESS_OPERATION'
        );
    }

    public function destroy(int $plan): JsonResponse
    {
        try {
            $this->planService->delete($plan);
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::error('CONFLICT', $e->getMessage(), null, 409);
        }

        return ApiResponseService::success(null, 'SUCCESS_OPERATION');
    }

    /**
     * Sincroniza todos os entitlements do plano.
     * Substitui o conjunto atual pelo payload enviado.
     */
    public function syncEntitlements(SyncPlanEntitlementsRequest $request, int $plan): JsonResponse
    {
        try {
            $model = $this->planService->syncEntitlements($plan, $request->validated()['entitlements']);
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::error('INVALID_ENTITLEMENT', $e->getMessage(), null, 422);
        }

        return ApiResponseService::success(
            new AdminPlanResource($model),
            'SUCCESS_OPERATION'
        );
    }
}
