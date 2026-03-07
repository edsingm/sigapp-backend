<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\Tenant\MobilePushService;
use Illuminate\Http\Request;

class MobileDeviceController extends Controller
{
    public function __construct(
        protected MobilePushService $mobilePushService
    ) {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'installation_id' => ['required', 'string', 'max:255'],
            'expo_push_token' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:64'],
            'platform' => ['required', 'string', 'max:32'],
            'tenant_id' => ['nullable', 'string'],
        ]);

        if (isset($data['tenant_id']) && (string) $data['tenant_id'] !== (string) tenant('id')) {
            return ApiResponseService::forbidden('Tenant inválido para este dispositivo');
        }

        $device = $this->mobilePushService->registerDevice($request->user(), $data);

        return ApiResponseService::success($device, 'Dispositivo registrado com sucesso');
    }

    public function destroy(Request $request, string $installationId)
    {
        $this->mobilePushService->unregisterDevice($request->user(), $installationId);

        return ApiResponseService::noContent();
    }
}
