<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\DestroyTerrenoProdutoRequest;
use App\Http\Requests\Tenant\Admin\ListTerrenoProdutosRequest;
use App\Http\Requests\Tenant\Admin\ShowTerrenoProdutoRequest;
use App\Http\Requests\Tenant\StoreTerrenoProdutoRequest;
use App\Http\Requests\Tenant\UpdateTerrenoProdutoRequest;
use App\Http\Resources\Tenant\TerrenoProdutoResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\TerrenoProdutoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TerrenoProdutosController extends Controller
{
    public function __construct(
        private readonly TerrenoProdutoService $terrenoProdutoService,
    ) {}

    public function index(ListTerrenoProdutosRequest $request): AnonymousResourceCollection
    {
        $perPage = $request->integer('per_page', 10);
        $terrenoId = $request->has('terreno_id') ? $request->integer('terreno_id') : null;
        $terrenoProdutos = $this->terrenoProdutoService->list($perPage, $terrenoId);

        return TerrenoProdutoResource::collection($terrenoProdutos)
            ->additional([
                'message' => 'Associações terreno-produto recuperadas com sucesso',
                'current_page' => $terrenoProdutos->currentPage(),
                'last_page' => $terrenoProdutos->lastPage(),
                'total' => $terrenoProdutos->total(),
                'per_page' => $terrenoProdutos->perPage(),
            ]);
    }

    public function show(ShowTerrenoProdutoRequest $request, int $id): JsonResponse
    {
        $terrenoProduto = $this->terrenoProdutoService->findById($id);

        if (! $terrenoProduto) {
            return ApiResponseService::notFound('Associação terreno-produto não encontrada');
        }

        return ApiResponseService::success(
            new TerrenoProdutoResource($terrenoProduto),
            'Associação terreno-produto recuperada com sucesso'
        );
    }

    public function store(StoreTerrenoProdutoRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;

        $terrenoProduto = $this->terrenoProdutoService->create($validated);

        return ApiResponseService::created(
            new TerrenoProdutoResource($terrenoProduto),
            'Associação terreno-produto criada com sucesso'
        );
    }

    public function update(UpdateTerrenoProdutoRequest $request, int $id): JsonResponse
    {
        $terrenoProduto = $this->terrenoProdutoService->findById($id);

        if (! $terrenoProduto) {
            return ApiResponseService::notFound('Associação terreno-produto não encontrada');
        }

        $validated = $request->validated();
        $validated['updated_by'] = $request->user()->id;

        $terrenoProduto = $this->terrenoProdutoService->update($terrenoProduto, $validated);

        return ApiResponseService::success(
            new TerrenoProdutoResource($terrenoProduto),
            'Associação terreno-produto atualizada com sucesso'
        );
    }

    public function destroy(DestroyTerrenoProdutoRequest $request, int $id): JsonResponse
    {
        $terrenoProduto = $this->terrenoProdutoService->findById($id);

        if (! $terrenoProduto) {
            return ApiResponseService::notFound('Associação terreno-produto não encontrada');
        }

        $this->terrenoProdutoService->delete($terrenoProduto);

        return ApiResponseService::success(null, 'Associação terreno-produto excluída com sucesso');
    }

    public function byTerreno(ListTerrenoProdutosRequest $request, int $terrenoId): JsonResponse
    {
        $terrenoProdutos = $this->terrenoProdutoService->byTerreno($terrenoId);

        return ApiResponseService::success(
            TerrenoProdutoResource::collection($terrenoProdutos),
            'Associações terreno-produto recuperadas com sucesso'
        );
    }
}
