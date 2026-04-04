<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddTenantEntitlementRequest;
use App\Http\Requests\Admin\AssignTenantPlanRequest;
use App\Http\Requests\Admin\UpdateTenantEntitlementRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\TenantEntitlementResource;
use App\Services\ApiResponseService;
use App\Services\TenantPlanService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

class TenantPlanController extends Controller
{
    public function __construct(
        private readonly TenantPlanService $tenantPlanService
    ) {}

    /**
     * Atribui um plano ao tenant, substituindo o plano atual.
     */
    public function assignPlan(AssignTenantPlanRequest $request, string $id)
    {
        try {
            $tenant = $this->tenantPlanService->assignPlan($id, $request->validated()['plan_id']);
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::error('INVALID_PLAN', $e->getMessage(), null, 422);
        }

        $tenant->load('plan');

        return ApiResponseService::success(
            new PlanResource($tenant->plan),
            'SUCCESS_OPERATION'
        );
    }

    /**
     * Realiza upgrade de plano (novo plano deve ter sort_order superior ao atual).
     */
    public function upgradePlan(AssignTenantPlanRequest $request, string $id)
    {
        try {
            $tenant = $this->tenantPlanService->upgradePlan($id, $request->validated()['plan_id']);
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::error('UPGRADE_FAILED', $e->getMessage(), null, 422);
        }

        $tenant->load('plan');

        return ApiResponseService::success(
            new PlanResource($tenant->plan),
            'SUCCESS_OPERATION'
        );
    }

    /**
     * Realiza downgrade de plano (novo plano deve ter sort_order inferior ao atual).
     */
    public function downgradePlan(AssignTenantPlanRequest $request, string $id)
    {
        try {
            $tenant = $this->tenantPlanService->downgradePlan($id, $request->validated()['plan_id']);
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::error('DOWNGRADE_FAILED', $e->getMessage(), null, 422);
        }

        $tenant->load('plan');

        return ApiResponseService::success(
            new PlanResource($tenant->plan),
            'SUCCESS_OPERATION'
        );
    }

    /**
     * Lista os entitlements extras de um tenant.
     */
    public function extraEntitlements(string $id)
    {
        try {
            $extras = $this->tenantPlanService->listExtraEntitlements($id);
        } catch (ModelNotFoundException) {
            return ApiResponseService::notFound(language()->t('RESOURCE_NOT_FOUND'));
        }

        return ApiResponseService::success(TenantEntitlementResource::collection($extras));
    }

    /**
     * Adiciona um entitlement extra ao tenant.
     */
    public function addExtraEntitlement(AddTenantEntitlementRequest $request, string $id)
    {
        $data = $request->validated();

        try {
            $record = $this->tenantPlanService->addExtraEntitlement(
                $id,
                (int) $data['entitlement_id'],
                $data['value'],
                (int) $data['price'],
            );
        } catch (UniqueConstraintViolationException) {
            return ApiResponseService::conflict('RESOURCE_ALREADY_EXISTS');
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::error('INVALID_ENTITLEMENT', $e->getMessage(), null, 422);
        }

        $record->load('entitlement');

        return ApiResponseService::created(new TenantEntitlementResource($record));
    }

    /**
     * Atualiza o valor e/ou preço de um entitlement extra do tenant.
     */
    public function updateExtraEntitlement(UpdateTenantEntitlementRequest $request, string $id, int $entitlementId)
    {
        try {
            $record = $this->tenantPlanService->updateExtraEntitlement(
                $id,
                $entitlementId,
                $request->validated()
            );
        } catch (ModelNotFoundException) {
            return ApiResponseService::notFound(language()->t('RESOURCE_NOT_FOUND'));
        }

        $record->load('entitlement');

        return ApiResponseService::success(new TenantEntitlementResource($record));
    }

    /**
     * Remove um entitlement extra do tenant.
     */
    public function removeExtraEntitlement(string $id, int $entitlementId)
    {
        try {
            $this->tenantPlanService->removeExtraEntitlement($id, $entitlementId);
        } catch (ModelNotFoundException) {
            return ApiResponseService::notFound(language()->t('RESOURCE_NOT_FOUND'));
        }

        return ApiResponseService::success(null, 'SUCCESS_OPERATION');
    }
}
