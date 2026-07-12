<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateUserPreferencesRequest;
use App\Services\ApiResponseService;
use App\Services\Tenant\UserPreferencesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class UserPreferencesController extends Controller
{
    public function __construct(
        private readonly UserPreferencesService $service,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponseService::success($this->service->get($request->user()), 'Preferências carregadas com sucesso');
    }

    public function update(UpdateUserPreferencesRequest $request): JsonResponse
    {
        try {
            return ApiResponseService::success($this->service->update($request->user(), $request->validated()), 'Preferências atualizadas com sucesso');
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('PREFERENCES_INVALID', $exception->getMessage(), null, 422);
        }
    }
}
