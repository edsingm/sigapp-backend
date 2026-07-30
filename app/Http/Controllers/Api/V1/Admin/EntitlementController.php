<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEntitlementRequest;
use App\Http\Requests\Admin\UpdateEntitlementRequest;
use App\Http\Resources\EntitlementResource;
use App\Services\ApiResponseService;
use App\Services\EntitlementService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class EntitlementController extends Controller
{
    public function __construct(
        private readonly EntitlementService $entitlementService
    ) {}

    public function index(): JsonResponse
    {
        $entitlements = $this->entitlementService->list();

        return ApiResponseService::success(
            EntitlementResource::collection($entitlements),
            'Entitlements recuperados com sucesso'
        );
    }

    public function show(int $entitlement): JsonResponse
    {
        try {
            $model = $this->entitlementService->findOrFail($entitlement);
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::notFound($e->getMessage());
        }

        return ApiResponseService::success(new EntitlementResource($model));
    }

    public function store(StoreEntitlementRequest $request): JsonResponse
    {
        try {
            $model = $this->entitlementService->create($request->validated());
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::error('INVALID_ENTITLEMENT', $e->getMessage(), null, 422);
        }

        return ApiResponseService::success(
            new EntitlementResource($model),
            'Entitlement criado com sucesso',
            201
        );
    }

    public function update(UpdateEntitlementRequest $request, int $entitlement): JsonResponse
    {
        try {
            $model = $this->entitlementService->update($entitlement, $request->validated());
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::error('INVALID_ENTITLEMENT', $e->getMessage(), null, 422);
        }

        return ApiResponseService::success(
            new EntitlementResource($model),
            'Entitlement atualizado com sucesso'
        );
    }

    public function destroy(int $entitlement): JsonResponse
    {
        try {
            $this->entitlementService->delete($entitlement);
        } catch (InvalidArgumentException $e) {
            return ApiResponseService::notFound($e->getMessage());
        }

        return ApiResponseService::success(null, 'Entitlement removido com sucesso');
    }
}
