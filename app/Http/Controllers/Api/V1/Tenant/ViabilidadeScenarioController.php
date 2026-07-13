<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListViabilidadeScenariosRequest;
use App\Http\Requests\Tenant\StoreViabilidadeScenarioRequest;
use App\Http\Requests\Tenant\UpdateViabilidadeScenarioRequest;
use App\Http\Resources\Tenant\ViabilidadeResource;
use App\Http\Resources\Tenant\ViabilidadeScenarioResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\ViabilidadeScenarioService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ViabilidadeScenarioController extends Controller
{
    public function __construct(
        private readonly ViabilidadeScenarioService $service,
    ) {}

    public function index(ListViabilidadeScenariosRequest $request, int $viabilidade): JsonResponse
    {
        return ApiResponseService::success(
            ViabilidadeScenarioResource::collection($this->service->list($viabilidade)),
        );
    }

    public function store(StoreViabilidadeScenarioRequest $request, int $viabilidade): JsonResponse
    {
        try {
            $scenario = $this->service->create($viabilidade, $request->validated(), $request->user());

            return ApiResponseService::created(new ViabilidadeScenarioResource($scenario));
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('SCENARIO_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function show(ListViabilidadeScenariosRequest $request, int $viabilidade, int $scenario): JsonResponse
    {
        return ApiResponseService::success(new ViabilidadeScenarioResource($this->service->find($viabilidade, $scenario)));
    }

    public function update(UpdateViabilidadeScenarioRequest $request, int $viabilidade, int $scenario): JsonResponse
    {
        try {
            $model = $this->service->find($viabilidade, $scenario);
            $updated = $this->service->update($model, $request->validated());

            return ApiResponseService::success(new ViabilidadeScenarioResource($updated));
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('SCENARIO_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function destroy(ListViabilidadeScenariosRequest $request, int $viabilidade, int $scenario): JsonResponse
    {
        $model = $this->service->find($viabilidade, $scenario);
        $this->service->delete($model);

        return ApiResponseService::noContent();
    }

    public function calculate(ListViabilidadeScenariosRequest $request, int $viabilidade, int $scenario): JsonResponse
    {
        try {
            $model = $this->service->find($viabilidade, $scenario);
            $calculated = $this->service->calculate($model, $request->user());

            return ApiResponseService::success(new ViabilidadeScenarioResource($calculated));
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('SCENARIO_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function promote(ListViabilidadeScenariosRequest $request, int $viabilidade, int $scenario): JsonResponse
    {
        try {
            $model = $this->service->find($viabilidade, $scenario);
            $promoted = $this->service->promote($model, $request->user());

            return ApiResponseService::success(new ViabilidadeResource($promoted));
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('SCENARIO_PROMOTION_NOT_ALLOWED', $exception->getMessage(), null, 409);
        }
    }
}
