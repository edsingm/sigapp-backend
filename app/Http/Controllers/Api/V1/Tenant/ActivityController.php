<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListActivitiesRequest;
use App\Http\Resources\Tenant\EntityActivityResource;
use App\Services\Tenant\ActivityService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ActivityController extends Controller
{
    public function __construct(
        private readonly ActivityService $activityService,
    ) {}

    public function index(ListActivitiesRequest $request): JsonResponse
    {
        try {
            $activities = $this->activityService->paginate($request->validated());

            return EntityActivityResource::collection($activities)->response();
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_ACTIVITY_FILTER',
                    'message' => $exception->getMessage(),
                ],
            ], 422);
        }
    }

    public function forEntity(
        ListActivitiesRequest $request,
        string $entityType,
        int $entityId,
    ): JsonResponse {
        $filters = $request->validated();
        $filters['entity_type'] = $entityType;
        $filters['entity_id'] = $entityId;

        try {
            $activities = $this->activityService->paginate($filters);

            return EntityActivityResource::collection($activities)->response();
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_ACTIVITY_FILTER',
                    'message' => $exception->getMessage(),
                ],
            ], 422);
        }
    }
}
