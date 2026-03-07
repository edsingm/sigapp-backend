<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\Tenant\TerrenoProdutoResource;
use App\Models\Tenant\TerrenoProduto;
use App\Http\Requests\Tenant\StoreTerrenoProdutoRequest;
use App\Http\Requests\Tenant\UpdateTerrenoProdutoRequest;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TerrenoProdutosController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TerrenoProduto::class);

        $tenantId = tenant('id') ?? 'central';
        $forceRefresh = $request->boolean('force_refresh', false);
        $filters = $request->only(['per_page', 'page', 'terreno_id']);
        $cacheKey = "tenant:{$tenantId}:terreno_produtos:index:" . md5(json_encode($filters));
        $cacheStore = \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:terreno_produtos"]);

        $resolver = function () use ($request) {
            $perPage = $request->integer('per_page', 10);
            $terrenoId = $request->input('terreno_id');

            $query = TerrenoProduto::with(['terreno', 'produto', 'createdBy', 'updatedBy'])
                ->orderBy('created_at', 'desc');

            if ($terrenoId) {
                $query->where('terreno_id', $terrenoId);
            }

            $paginator = $query->paginate($perPage);

            return $this->respondWithPagination($paginator, TerrenoProdutoResource::class);
        };

        if ($forceRefresh) {
            $cacheStore->forget($cacheKey);
            $freshData = $resolver();
            $cacheStore->put($cacheKey, $freshData, now()->addMinutes(30));
            return $freshData;
        }

        return $cacheStore->remember($cacheKey, now()->addMinutes(30), $resolver);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTerrenoProdutoRequest $request): JsonResponse
    {
        Gate::authorize('create', TerrenoProduto::class);

        $data = $request->validated();

        $userId = $request->user()->id ?? null;
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        $terrenoProduto = TerrenoProduto::create($data);

        $terrenoProduto->load(['terreno', 'produto']);

        return response()->json([
            'success' => true,
            'data' => new TerrenoProdutoResource($terrenoProduto),
            'message' => 'Produto do terreno criado com sucesso!',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $terrenoProduto = TerrenoProduto::with(['terreno', 'produto', 'createdBy', 'updatedBy'])
            ->findOrFail($id);
        Gate::authorize('view', $terrenoProduto);

        return response()->json([
            'success' => true,
            'data' => new TerrenoProdutoResource($terrenoProduto)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTerrenoProdutoRequest $request, string $id): JsonResponse
    {
        $terrenoProduto = TerrenoProduto::findOrFail($id);
        Gate::authorize('update', $terrenoProduto);

        $data = $request->validated();
        $userId = $request->user()->id ?? null;
        $data['updated_by'] = $userId;

        $terrenoProduto->update($data);

        $terrenoProduto->load(['terreno', 'produto']);

        return response()->json([
            'success' => true,
            'data' => new TerrenoProdutoResource($terrenoProduto),
            'message' => 'Produto do terreno atualizado com sucesso!',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $terrenoProduto = TerrenoProduto::findOrFail($id);
        Gate::authorize('delete', $terrenoProduto);
        $terrenoProduto->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produto do terreno removido com sucesso!',
        ]);
    }

    /**
     * Get terreno produtos by terreno_id.
     */
    public function byTerreno(string $terrenoId)
    {
        Gate::authorize('viewAny', TerrenoProduto::class);

        $terrenoProdutos = TerrenoProduto::with(['produto'])
            ->where('terreno_id', $terrenoId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => TerrenoProdutoResource::collection($terrenoProdutos),
        ]);
    }
}
