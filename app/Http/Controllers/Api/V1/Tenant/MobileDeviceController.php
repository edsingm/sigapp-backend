<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyMobileDeviceRequest;
use App\Http\Requests\Tenant\StoreMobileDeviceRequest;
use App\Http\Resources\Tenant\MobileDeviceInstallationResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\MobilePushService;
use Illuminate\Http\JsonResponse;

class MobileDeviceController extends Controller
{
    public function __construct(
        protected MobilePushService $mobilePushService
    ) {}

    /**
     * Registrar um novo dispositivo móvel.
     */
    public function store(StoreMobileDeviceRequest $request): JsonResponse
    {
        $device = $this->mobilePushService->registerDevice($request->user(), $request->validated());

        return ApiResponseService::success(
            new MobileDeviceInstallationResource($device),
            'Dispositivo registrado com sucesso'
        );
    }

    /**
     * Remover um dispositivo móvel registrado.
     */
    public function destroy(DestroyMobileDeviceRequest $request, string $installationId): JsonResponse
    {
        $this->mobilePushService->unregisterDevice($request->user(), $installationId);

        return ApiResponseService::noContent();
    }
}
