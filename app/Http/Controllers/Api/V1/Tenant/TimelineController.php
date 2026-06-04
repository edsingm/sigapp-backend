<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\TimelineEntryResource;
use App\Services\Tenant\TimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimelineController extends Controller
{
    public function __construct(
        private readonly TimelineService $timelineService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        $page = (int) $request->integer('page', 1);
        $perPage = (int) min($request->integer('per_page', 50), 100);

        $paginator = $this->timelineService->getForTerreno($id, $page, $perPage);

        return response()->json(TimelineEntryResource::collection($paginator));
    }
}
