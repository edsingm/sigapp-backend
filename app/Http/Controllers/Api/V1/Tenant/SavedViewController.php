<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListSavedViewsRequest;
use App\Http\Requests\Tenant\StoreSavedViewRequest;
use App\Http\Requests\Tenant\UpdateSavedViewRequest;
use App\Http\Resources\Tenant\SavedViewResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\SavedViewService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class SavedViewController extends Controller
{
    public function __construct(
        private readonly SavedViewService $service,
    ) {}

    public function index(ListSavedViewsRequest $request): JsonResponse
    {
        try {
            $result = $this->service->paginate($request->user(), $request->validated());
            $result->through(fn ($view): array => (new SavedViewResource($view))->resolve());

            return ApiResponseService::paginated($result, 'Visões salvas carregadas com sucesso');
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('SAVED_VIEW_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function store(StoreSavedViewRequest $request): JsonResponse
    {
        try {
            return ApiResponseService::created(
                new SavedViewResource($this->service->create($request->user(), $request->validated())),
                'Visão salva criada com sucesso',
            );
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('SAVED_VIEW_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function show(string $id, ListSavedViewsRequest $request): JsonResponse
    {
        return ApiResponseService::success(new SavedViewResource($this->service->find($request->user(), (int) $id)));
    }

    public function update(UpdateSavedViewRequest $request, string $id): JsonResponse
    {
        try {
            $view = $this->service->find($request->user(), (int) $id);

            return ApiResponseService::success(
                new SavedViewResource($this->service->update($request->user(), $view, $request->validated())),
                'Visão salva atualizada com sucesso',
            );
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('SAVED_VIEW_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function destroy(ListSavedViewsRequest $request, string $id): JsonResponse
    {
        try {
            $this->service->delete($request->user(), $this->service->find($request->user(), (int) $id));

            return ApiResponseService::noContent();
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('SAVED_VIEW_INVALID', $exception->getMessage(), null, 422);
        }
    }

    public function setDefault(ListSavedViewsRequest $request, string $id): JsonResponse
    {
        try {
            return ApiResponseService::success(
                new SavedViewResource($this->service->setDefault($request->user(), $this->service->find($request->user(), (int) $id))),
                'Visão salva definida como padrão',
            );
        } catch (RuntimeException $exception) {
            return ApiResponseService::error('SAVED_VIEW_INVALID', $exception->getMessage(), null, 422);
        }
    }
}
