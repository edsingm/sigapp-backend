<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CompareTerrenosRequest;
use App\Http\Requests\Tenant\ListShortlistsRequest;
use App\Http\Requests\Tenant\ShortlistItemRequest;
use App\Http\Requests\Tenant\StoreShortlistRequest;
use App\Http\Requests\Tenant\UpdateShortlistRequest;
use App\Http\Resources\Tenant\ShortlistResource;
use App\Http\Resources\Tenant\TerrenoComparisonResource;
use App\Models\Tenant\ShortlistItem;
use App\Services\ApiResponseService;
use App\Services\Tenant\ShortlistService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ShortlistController extends Controller
{
    public function __construct(
        private readonly ShortlistService $service,
    ) {}

    public function index(ListShortlistsRequest $request): JsonResponse
    {
        $paginator = $this->service->paginate(
            (int) $request->user()->getAuthIdentifier(),
            $request->validated('scope'),
            (int) $request->validated('page', 1),
            (int) $request->validated('per_page', 30),
        );

        return ShortlistResource::collection($paginator)->response();
    }

    public function store(StoreShortlistRequest $request): JsonResponse
    {
        try {
            $shortlist = $this->service->create(
                $request->validated(),
                (int) $request->user()->getAuthIdentifier(),
            );

            return ApiResponseService::created(new ShortlistResource($shortlist->load(['owner', 'items.terreno'])));
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('SHORTLIST_NOT_ALLOWED', $exception->getMessage(), null, 403);
        }
    }

    public function show(ListShortlistsRequest $request, int $shortlist): JsonResponse
    {
        return ApiResponseService::success(new ShortlistResource($this->service->find(
            $shortlist,
            (int) $request->user()->getAuthIdentifier(),
        )));
    }

    public function update(UpdateShortlistRequest $request, int $shortlist): JsonResponse
    {
        try {
            $model = $this->service->find($shortlist, (int) $request->user()->getAuthIdentifier());
            $updated = $this->service->update($model, $request->validated(), (int) $request->user()->getAuthIdentifier());

            return ApiResponseService::success(new ShortlistResource($updated));
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('SHORTLIST_NOT_ALLOWED', $exception->getMessage(), null, 403);
        }
    }

    public function destroy(ListShortlistsRequest $request, int $shortlist): JsonResponse
    {
        try {
            $model = $this->service->find($shortlist, (int) $request->user()->getAuthIdentifier());
            $this->service->delete($model, (int) $request->user()->getAuthIdentifier());

            return ApiResponseService::noContent();
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('SHORTLIST_NOT_ALLOWED', $exception->getMessage(), null, 403);
        }
    }

    public function addItem(ShortlistItemRequest $request, int $shortlist): JsonResponse
    {
        $model = $this->service->find($shortlist, (int) $request->user()->getAuthIdentifier());
        /** @var ShortlistItem $item */
        $item = $this->service->addItem($model, (int) $request->validated('terreno_id'));

        return ApiResponseService::created([
            'id' => $item->getAttribute('id'),
            'shortlist_id' => $item->getAttribute('shortlist_id'),
            'terreno_id' => $item->getAttribute('terreno_id'),
            'position' => $item->getAttribute('position'),
        ]);
    }

    public function removeItem(ListShortlistsRequest $request, int $shortlist, int $terreno): JsonResponse
    {
        $model = $this->service->find($shortlist, (int) $request->user()->getAuthIdentifier());
        $this->service->removeItem($model, $terreno);

        return ApiResponseService::noContent();
    }

    public function compare(CompareTerrenosRequest $request): JsonResponse
    {
        try {
            $terrenos = $this->service->compare($request->validated('terreno_ids'));

            return ApiResponseService::success([
                'items' => TerrenoComparisonResource::collection($terrenos)->resolve(),
                'count' => count($terrenos),
                'recommendation' => null,
            ]);
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('COMPARISON_INVALID', $exception->getMessage(), null, 422);
        }
    }
}
