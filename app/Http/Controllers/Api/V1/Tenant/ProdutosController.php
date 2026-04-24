<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\DestroyProdutoRequest;
use App\Http\Requests\Tenant\Admin\ListProdutosRequest;
use App\Http\Requests\Tenant\Admin\RestoreProdutoRequest;
use App\Http\Requests\Tenant\Admin\ShowProdutoRequest;
use App\Http\Requests\Tenant\StoreProdutoRequest;
use App\Http\Requests\Tenant\UpdateProdutoRequest;
use App\Http\Resources\Tenant\ProdutoResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\ProdutoService;

class ProdutosController extends Controller
{
    public function __construct(
        private readonly ProdutoService $produtoService,
    ) {}

    public function index(ListProdutosRequest $request)
    {
        $perPage = $request->integer('per_page', 10);
        $produtos = $this->produtoService->list($perPage);

        return ProdutoResource::collection($produtos)
            ->additional([
                'message' => 'Produtos recuperados com sucesso',
                'current_page' => $produtos->currentPage(),
                'last_page' => $produtos->lastPage(),
                'total' => $produtos->total(),
                'per_page' => $produtos->perPage(),
            ]);
    }

    public function forSelect(ListProdutosRequest $request)
    {
        $search = $request->string('search')->toString();
        $produtos = $this->produtoService->searchForSelect($search);

        return ApiResponseService::success($produtos, 'Produtos recuperados com sucesso');
    }

    public function show(ShowProdutoRequest $request, int $id)
    {
        $produto = $this->produtoService->findById($id);

        if (! $produto) {
            return ApiResponseService::notFound('Produto não encontrado');
        }

        return ApiResponseService::success(
            new ProdutoResource($produto),
            'Produto recuperado com sucesso'
        );
    }

    public function store(StoreProdutoRequest $request)
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;

        $produto = $this->produtoService->create($validated);

        return ApiResponseService::created(
            new ProdutoResource($produto),
            'Produto criado com sucesso'
        );
    }

    public function update(UpdateProdutoRequest $request, int $id)
    {
        $produto = $this->produtoService->findById($id);

        if (! $produto) {
            return ApiResponseService::notFound('Produto não encontrado');
        }

        $validated = $request->validated();
        $validated['updated_by'] = $request->user()->id;

        $produto = $this->produtoService->update($produto, $validated);

        return ApiResponseService::success(
            new ProdutoResource($produto),
            'Produto atualizado com sucesso'
        );
    }

    public function destroy(DestroyProdutoRequest $request, int $id)
    {
        $produto = $this->produtoService->findById($id);

        if (! $produto) {
            return ApiResponseService::notFound('Produto não encontrado');
        }

        $this->produtoService->delete($produto);

        return ApiResponseService::success(null, 'Produto excluído com sucesso');
    }

    public function restore(RestoreProdutoRequest $request, int $id)
    {
        $produto = $this->produtoService->findById($id, withTrashed: true);

        if (! $produto) {
            return ApiResponseService::notFound('Produto não encontrado');
        }

        if (! $produto->trashed()) {
            return ApiResponseService::success(null, 'O produto já está ativo');
        }

        $this->produtoService->restore($produto);

        return ApiResponseService::success(
            new ProdutoResource($produto),
            'Produto restaurado com sucesso'
        );
    }
}
