<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantAddonSubscriptionResource;
use App\Models\Central\Tenant;
use App\Services\ApiResponseService;
use App\Services\Billing\TenantAddonAdminService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class TenantAddonAdminController extends Controller
{
    public function __construct(
        private readonly TenantAddonAdminService $service,
    ) {}

    public function index(Tenant $tenant): JsonResponse
    {
        return ApiResponseService::success(
            TenantAddonSubscriptionResource::collection($this->service->subscriptions($tenant)),
            'DATA_RETRIEVED_SUCCESSFULLY',
        );
    }

    public function reconcile(Tenant $tenant): JsonResponse
    {
        try {
            $result = $this->service->reconcile($tenant);
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('BILLING_RECONCILIATION_ERROR', $exception->getMessage(), null, 422);
        } catch (\Throwable $exception) {
            return ApiResponseService::error(
                'BILLING_RECONCILIATION_ERROR',
                'UNKNOWN_ERROR',
                app()->environment('local') ? $exception->getMessage() : null,
                502,
            );
        }

        return ApiResponseService::success([
            'reconciliation' => $result,
            'addons' => TenantAddonSubscriptionResource::collection(
                $this->service->subscriptions($tenant->refresh()),
            ),
        ], 'SUCCESS_OPERATION');
    }

    public function accessMatrix(Tenant $tenant): JsonResponse
    {
        try {
            return ApiResponseService::success($this->service->accessMatrix($tenant));
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('ACCESS_MATRIX_ERROR', $exception->getMessage(), null, 422);
        }
    }
}
