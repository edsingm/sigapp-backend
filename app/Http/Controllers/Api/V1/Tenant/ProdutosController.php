<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\Tenant\ProdutoResource;
use App\Models\Tenant\Produto;
use App\Http\Requests\Tenant\StoreProdutoRequest;
use App\Http\Requests\Tenant\UpdateProdutoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProdutosController extends Controller
{
    /**
     * Exibe uma listagem do recurso.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Produto::class);

        $tenantId = tenant('id') ?? 'central';
        $filters = $request->only(['per_page', 'page']);
        $cacheKey = "tenant:{$tenantId}:produtos:index:" . md5(json_encode($filters));

        return \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:produtos"])->remember($cacheKey, now()->addMinutes(30), function () use ($request) {
            $perPage = (int) ($request->input('per_page') ?? 10);
            $paginator = Produto::orderBy('created_at', 'desc')
                ->paginate($perPage);

            return $this->respondWithPagination($paginator, ProdutoResource::class);
        });
    }

    /**
     * Armazena um recurso recém-criado.
     */
    public function store(StoreProdutoRequest $request): JsonResponse
    {
        Gate::authorize('create', Produto::class);

        $data = $request->validated();
        $produto = Produto::create($data);

        return response()->json([
            'success' => true,
            'data' => new ProdutoResource($produto),
            'message' => 'Produto criado com sucesso!',
        ], 201);
    }

    /**
     * Exibe o recurso especificado.
     */
    public function show(string $id): JsonResponse
    {
        $produto = Produto::withTrashed()->findOrFail($id);
        Gate::authorize('view', $produto);

        return response()->json([
            'success' => true,
            'data' => new ProdutoResource($produto)
        ]);
    }

    /**
     * Atualiza o recurso especificado.
     */
    public function update(UpdateProdutoRequest $request, string $id): JsonResponse
    {
        $produto = Produto::findOrFail($id);
        Gate::authorize('update', $produto);
        $data = $request->validated();

        $produto->update($data);

        return response()->json([
            'success' => true,
            'data' => new ProdutoResource($produto),
            'message' => 'Produto atualizado com sucesso!',
        ]);
    }

    /**
     * Remove o recurso especificado.
     */
    public function destroy(string $id): JsonResponse
    {
        $produto = Produto::findOrFail($id);
        Gate::authorize('delete', $produto);
        $produto->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produto excluído com sucesso!',
        ]);
    }

    /**
     * Restaura o recurso especificado.
     */
    public function restore(string $id): JsonResponse
    {
        $produto = Produto::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $produto);
        $produto->restore();

        return response()->json([
            'success' => true,
            'data' => new ProdutoResource($produto),
            'message' => 'Produto restaurado com sucesso!',
        ]);
    }

    /**
     * Listar produtos para seleção.
     */
    public function produtosForSelect(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Produto::class);

        $search = $request->input('search', '');

        $produtos = Produto::where('name', 'like', '%' . $search . '%')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $produtos,
        ]);
    }
}
