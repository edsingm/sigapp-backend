<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DiscardPendingPolygonRequest;
use App\Http\Requests\Tenant\LinkPendingPolygonRequest;
use App\Http\Requests\Tenant\ListPendingPolygonsRequest;
use App\Http\Requests\Tenant\StoreTerrenoPolygonImportRequest;
use App\Http\Resources\Tenant\TerrenoPendingPolygonResource;
use App\Http\Resources\Tenant\TerrenoPolygonImportResource;
use App\Models\Tenant\User;
use App\Services\ApiResponseService;
use App\Services\Tenant\TerrenoPolygonImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

class TerrenoPolygonImportController extends Controller
{
    public function __construct(private readonly TerrenoPolygonImportService $imports) {}

    public function store(StoreTerrenoPolygonImportRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $files = $request->file('arquivos');
        if (! is_array($files)) {
            throw new LogicException('Os arquivos geográficos validados não foram encontrados.');
        }
        $import = $this->imports->create(
            $user,
            (string) $request->validated('idempotency_key'),
            array_values($files),
        );

        return ApiResponseService::success(new TerrenoPolygonImportResource($import), 'POLYGON_IMPORT_QUEUED', 202);
    }

    public function show(Request $request, int $import): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponseService::success(new TerrenoPolygonImportResource($this->imports->find($user, $import)));
    }

    public function index(ListPendingPolygonsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $features = TerrenoPendingPolygonResource::collection($this->imports->polygons(
            (float) $data['min_lng'],
            (float) $data['min_lat'],
            (float) $data['max_lng'],
            (float) $data['max_lat'],
            (int) ($data['limit'] ?? 500),
        ))->resolve();

        return ApiResponseService::success([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    public function link(LinkPendingPolygonRequest $request, int $polygon): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $linked = $this->imports->link($polygon, (int) $request->validated('terreno_id'), $user);

        return ApiResponseService::success(new TerrenoPendingPolygonResource($linked), 'POLYGON_LINKED');
    }

    public function destroy(DiscardPendingPolygonRequest $request, int $polygon): JsonResponse
    {
        $this->imports->discard($polygon);

        return ApiResponseService::noContent();
    }
}
