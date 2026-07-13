<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\OnboardingEventRequest;
use App\Http\Resources\Tenant\UserOnboardingResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\UserOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserOnboardingController extends Controller
{
    public function __construct(private readonly UserOnboardingService $service) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponseService::success(new UserOnboardingResource($this->service->get($request->user())));
    }

    public function event(OnboardingEventRequest $request): JsonResponse
    {
        return ApiResponseService::success(
            new UserOnboardingResource($this->service->event($request->user(), $request->validated())),
            'Evento de onboarding registrado com sucesso',
        );
    }

    public function dismiss(Request $request): JsonResponse
    {
        return ApiResponseService::success(new UserOnboardingResource($this->service->dismiss($request->user())));
    }

    public function resume(Request $request): JsonResponse
    {
        return ApiResponseService::success(new UserOnboardingResource($this->service->resume($request->user())));
    }
}
