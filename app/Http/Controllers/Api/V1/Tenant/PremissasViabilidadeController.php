<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StorePremissasViabilidadeRequest;
use App\Http\Requests\Tenant\UpdatePremissasViabilidadeRequest;
use App\Http\Resources\Tenant\PremissasViabilidadeResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\PremissasViabilidadeCrudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PremissasViabilidadeController extends Controller
{
    public function __construct(
        private readonly PremissasViabilidadeCrudService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->integer('per_page', 10);
        $perfil = $request->string('perfil_financiamento')->toString() ?: null;

        $premissas = $this->service->list($perfil, $perPage);

        return PremissasViabilidadeResource::collection($premissas)
            ->additional([
                'message' => 'Premissas recuperadas com sucesso',
                'current_page' => $premissas->currentPage(),
                'last_page' => $premissas->lastPage(),
                'total' => $premissas->total(),
                'per_page' => $premissas->perPage(),
            ]);
    }

    public function show(int $id): JsonResponse
    {
        $premissa = $this->service->findById($id);

        if (! $premissa) {
            return ApiResponseService::notFound('Premissas não encontradas');
        }

        return ApiResponseService::success(
            new PremissasViabilidadeResource($premissa),
            'Premissas recuperadas com sucesso'
        );
    }

    public function store(StorePremissasViabilidadeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;

        $premissa = $this->service->create($validated);

        return ApiResponseService::created(
            new PremissasViabilidadeResource($premissa),
            'Premissas criadas com sucesso'
        );
    }

    public function update(UpdatePremissasViabilidadeRequest $request, int $id): JsonResponse
    {
        $premissa = $this->service->findById($id);

        if (! $premissa) {
            return ApiResponseService::notFound('Premissas não encontradas');
        }

        $validated = $request->validated();
        $validated['updated_by'] = $request->user()->id;

        $premissa = $this->service->update($premissa, $validated);

        return ApiResponseService::success(
            new PremissasViabilidadeResource($premissa),
            'Premissas atualizadas com sucesso'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $premissa = $this->service->findById($id);

        if (! $premissa) {
            return ApiResponseService::notFound('Premissas não encontradas');
        }

        $this->service->delete($premissa);

        return ApiResponseService::success(null, 'Premissas excluídas com sucesso');
    }
}
