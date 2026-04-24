<?php

namespace App\Http\Controllers\Api\V1\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StorePositionRequest;
use App\Http\Requests\Tenant\UpdatePositionRequest;
use App\Http\Resources\Tenant\PositionResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\PositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function __construct(
        private readonly PositionService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $positions = $this->service->list([
            'search' => $request->filled('search') ? $request->string('search')->toString() : null,
            'active' => $request->has('active') ? $request->boolean('active') : null,
            'sort' => $request->string('sort', 'level')->toString(),
            'order' => $request->string('order', 'asc')->toString(),
            'per_page' => (int) $request->integer('per_page', 15),
        ]);

        $positions->through(fn ($position) => (new PositionResource($position))->toArray($request));

        return ApiResponseService::paginated($positions, 'Positions retrieved successfully.');
    }

    public function forSelect(): JsonResponse
    {
        $positions = $this->service->listActiveForSelect();

        return ApiResponseService::success(
            PositionResource::collection($positions),
            'Positions loaded successfully.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $position = $this->service->findById($id);

        if (! $position) {
            return ApiResponseService::notFound('Position not found.');
        }

        return ApiResponseService::success(new PositionResource($position), 'Position retrieved successfully.');
    }

    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = $this->service->create($request->validated());

        return ApiResponseService::created(new PositionResource($position), 'Position created successfully.');
    }

    public function update(UpdatePositionRequest $request, int $id): JsonResponse
    {
        $position = $this->service->findById($id);

        if (! $position) {
            return ApiResponseService::notFound('Position not found.');
        }

        $position = $this->service->update($position, $request->validated());

        return ApiResponseService::success(new PositionResource($position), 'Position updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $position = $this->service->findById($id);

        if (! $position) {
            return ApiResponseService::notFound('Position not found.');
        }

        $error = $this->service->delete($position);

        if ($error === 'POSITION_IN_USE') {
            return ApiResponseService::error(
                'POSITION_IN_USE',
                'Cannot delete a position that has users assigned to it.',
                null,
                422
            );
        }

        return ApiResponseService::noContent();
    }
}
