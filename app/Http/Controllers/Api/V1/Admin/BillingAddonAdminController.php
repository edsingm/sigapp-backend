<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBillingAddonRequest;
use App\Http\Requests\Admin\UpdateBillingAddonRequest;
use App\Http\Resources\BillingAddonResource;
use App\Services\ApiResponseService;
use App\Services\Billing\BillingAddonService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class BillingAddonAdminController extends Controller
{
    public function __construct(
        private readonly BillingAddonService $service,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponseService::success(
            BillingAddonResource::collection($this->service->list()),
            'DATA_RETRIEVED_SUCCESSFULLY',
        );
    }

    public function show(int $billingAddon): JsonResponse
    {
        try {
            $addon = $this->service->findOrFail($billingAddon);
        } catch (InvalidArgumentException) {
            return ApiResponseService::notFound('BILLING_ADDON_NOT_FOUND');
        }

        return ApiResponseService::success(new BillingAddonResource($addon));
    }

    public function store(StoreBillingAddonRequest $request): JsonResponse
    {
        try {
            $addon = $this->service->create($request->validated());
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('INVALID_BILLING_ADDON', $exception->getMessage(), null, 422);
        }

        return ApiResponseService::created(new BillingAddonResource($addon));
    }

    public function update(UpdateBillingAddonRequest $request, int $billingAddon): JsonResponse
    {
        try {
            $addon = $this->service->update($billingAddon, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('INVALID_BILLING_ADDON', $exception->getMessage(), null, 422);
        }

        return ApiResponseService::success(new BillingAddonResource($addon), 'SUCCESS_OPERATION');
    }

    public function destroy(int $billingAddon): JsonResponse
    {
        try {
            $this->service->delete($billingAddon);
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('BILLING_ADDON_DELETE_CONFLICT', $exception->getMessage(), null, 409);
        }

        return ApiResponseService::success(null, 'SUCCESS_OPERATION');
    }
}
