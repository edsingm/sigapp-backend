<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTerrenoProdutoRequest;
use App\Http\Requests\Tenant\UpdateTerrenoProdutoRequest;
use App\Http\Resources\Tenant\TerrenoProdutoResource;
use App\Models\Tenant\TerrenoProduto;
use App\Services\Tenant\LandWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class TerrenoProdutosController extends Controller
{
    public function __construct(
        protected LandWorkflowService $workflowService,
    ) {}

    /**
     * Listar os produtos vinculados a terrenos.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TerrenoProduto::class);

        $tenantId = tenant('id') ?? 'central';
        $forceRefresh = $request->boolean('force_refresh', false);
        $filters = $request->only(['per_page', 'page', 'terreno_id']);
        $cacheKey = "tenant:{$tenantId}:terreno_produtos:v2:index:".md5(json_encode($filters));
        $cacheStore = Cache::tags(["tenant:{$tenantId}:terreno_produtos"]);

        $resolver = function () use ($request) {
            $perPage = $request->integer('per_page', 10);
            $terrenoId = $request->input('terreno_id');

            $query = TerrenoProduto::with([
                'terreno',
                'produto' => fn ($q) => $q->withTrashed(),
                'createdBy',
                'updatedBy',
            ])->orderBy('created_at', 'desc');

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
     * Vincular um novo produto a um terreno.
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
        $this->workflowService->syncReadiness($terrenoProduto->terreno()->firstOrFail(), $request->user(), 'land_product_created');

        return response()->json([
            'success' => true,
            'data' => new TerrenoProdutoResource($terrenoProduto),
            'message' => 'Produto do terreno criado com sucesso!',
        ], 201);
    }

    /**
     * Exibir os detalhes de um vínculo de produto específico.
     */
    public function show(string $id): JsonResponse
    {
        $terrenoProduto = TerrenoProduto::with(['terreno', 'produto', 'createdBy', 'updatedBy'])
            ->findOrFail($id);
        Gate::authorize('view', $terrenoProduto);

        return response()->json([
            'success' => true,
            'data' => new TerrenoProdutoResource($terrenoProduto),
        ]);
    }

    /**
     * Atualizar um vínculo de produto existente.
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
        $this->workflowService->syncReadiness($terrenoProduto->terreno()->firstOrFail(), $request->user(), 'land_product_updated');

        return response()->json([
            'success' => true,
            'data' => new TerrenoProdutoResource($terrenoProduto),
            'message' => 'Produto do terreno atualizado com sucesso!',
        ]);
    }

    /**
     * Remover o vínculo de um produto com um terreno.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $terrenoProduto = TerrenoProduto::findOrFail($id);
        Gate::authorize('delete', $terrenoProduto);
        $terreno = $terrenoProduto->terreno()->first();
        $terrenoProduto->delete();

        if ($terreno) {
            $this->workflowService->syncReadiness($terreno, $request->user(), 'land_product_deleted');
        }

        return response()->json([
            'success' => true,
            'message' => 'Produto do terreno removido com sucesso!',
        ]);
    }

    /**
     * Listar produtos vinculados a um terreno específico.
     */
    public function byTerreno(string $terrenoId)
    {
        Gate::authorize('viewAny', TerrenoProduto::class);

        $terrenoProdutos = TerrenoProduto::with(['produto' => fn ($q) => $q->withTrashed()])
            ->where('terreno_id', $terrenoId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => TerrenoProdutoResource::collection($terrenoProdutos),
        ]);
    }
}
