<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\DestroyRegionalRequest;
use App\Http\Requests\Tenant\Admin\ListRegionaisRequest;
use App\Http\Requests\Tenant\Admin\SelectRegionaisRequest;
use App\Http\Requests\Tenant\Admin\ShowRegionalRequest;
use App\Http\Requests\Tenant\StoreRegionalRequest;
use App\Http\Requests\Tenant\UpdateRegionalRequest;
use App\Http\Resources\Tenant\RegionalResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\RegionalService;

class RegionaisController extends Controller
{
    public function __construct(
        private readonly RegionalService $regionalService,
    ) {}

    public function index(ListRegionaisRequest $request)
    {
        $perPage = $request->integer('per_page', 10);
        $search = $request->has('q') ? $request->string('q')->toString() : null;
        $regionais = $this->regionalService->list($perPage, $search);

        return RegionalResource::collection($regionais)
            ->additional([
                'message' => 'Regionais recuperadas com sucesso',
                'current_page' => $regionais->currentPage(),
                'last_page' => $regionais->lastPage(),
                'total' => $regionais->total(),
                'per_page' => $regionais->perPage(),
            ]);
    }

    public function forSelect(SelectRegionaisRequest $request)
    {
        $regionais = $this->regionalService->forSelect();

        return ApiResponseService::success(
            $regionais->map(fn ($r) => ['id' => $r->id, 'nome' => $r->nome])->values(),
            'Regionais recuperadas com sucesso'
        );
    }

    public function show(ShowRegionalRequest $request, int $id)
    {
        $regional = $this->regionalService->findById($id);

        if (! $regional) {
            return ApiResponseService::notFound('Regional não encontrada');
        }

        return ApiResponseService::success(
            new RegionalResource($regional),
            'Regional recuperada com sucesso'
        );
    }

    public function store(StoreRegionalRequest $request)
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;

        $regional = $this->regionalService->create($validated);

        return ApiResponseService::created(
            new RegionalResource($regional),
            'Regional criada com sucesso'
        );
    }

    public function update(UpdateRegionalRequest $request, int $id)
    {
        $regional = $this->regionalService->findById($id);

        if (! $regional) {
            return ApiResponseService::notFound('Regional não encontrada');
        }

        $validated = $request->validated();
        $validated['updated_by'] = $request->user()->id;

        $regional = $this->regionalService->update($regional, $validated);

        return ApiResponseService::success(
            new RegionalResource($regional),
            'Regional atualizada com sucesso'
        );
    }

    public function destroy(DestroyRegionalRequest $request, int $id)
    {
        $regional = $this->regionalService->findById($id);

        if (! $regional) {
            return ApiResponseService::notFound('Regional não encontrada');
        }

        $this->regionalService->delete($regional);

        return ApiResponseService::success(null, 'Regional excluída com sucesso');
    }
}
