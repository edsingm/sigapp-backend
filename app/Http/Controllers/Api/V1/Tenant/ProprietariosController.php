<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProprietarioRequest;
use App\Http\Requests\Tenant\UpdateProprietarioRequest;
use App\Http\Resources\Tenant\ProprietarioResource;
use App\Models\Tenant\Proprietario;
use App\Services\Tenant\LandWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class ProprietariosController extends Controller
{
    public function __construct(
        protected LandWorkflowService $workflowService,
    ) {}

    /**
     * Listar proprietários.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Proprietario::class);

        $tenantId = tenant('id') ?? 'central';
        $filters = $request->only(['per_page', 'page', 'terreno_id']);

        $cacheKey = "tenant:{$tenantId}:proprietarios:index:".md5(json_encode($filters));

        return Cache::tags(["tenant:{$tenantId}:proprietarios"])->remember($cacheKey, now()->addMinutes(30), function () use ($request) {
            $perPage = (int) ($request->input('per_page') ?? 10);
            $terrenoId = $request->input('terreno_id');

            $query = Proprietario::with(['terreno', 'createdBy', 'updatedBy'])
                ->orderBy('created_at', 'desc');

            if ($terrenoId) {
                $query->where('terreno_id', $terrenoId);
            }

            $paginator = $query->paginate($perPage);

            return $this->respondWithPagination($paginator, ProprietarioResource::class);
        });
    }

    /**
     * Armazenar um novo proprietário.
     */
    public function store(StoreProprietarioRequest $request): JsonResponse
    {
        Gate::authorize('create', Proprietario::class);

        $data = $request->validated();
        $userId = $request->user()->id ?? null;

        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        $owner = Proprietario::create($data);
        $this->workflowService->syncReadiness($owner->terreno()->firstOrFail(), $request->user(), 'owner_created');

        return response()->json([
            'success' => true,
            'data' => new ProprietarioResource($owner),
            'message' => 'Proprietário criado com sucesso!',
        ], 201);
    }

    /**
     * Exibir os detalhes de um proprietário específico.
     */
    public function show(Proprietario $proprietario): JsonResponse
    {
        Gate::authorize('view', $proprietario);
        $proprietario->load(['terreno', 'createdBy', 'updatedBy']);

        return response()->json([
            'success' => true,
            'data' => new ProprietarioResource($proprietario),
        ]);
    }

    /**
     * Atualizar um proprietário existente.
     */
    public function update(UpdateProprietarioRequest $request, Proprietario $proprietario): JsonResponse
    {
        Gate::authorize('update', $proprietario);
        $data = $request->validated();
        $userId = $request->user()->id ?? null;

        $data['updated_by'] = $userId;

        $proprietario->update($data);
        $this->workflowService->syncReadiness($proprietario->terreno()->firstOrFail(), $request->user(), 'owner_updated');

        return response()->json([
            'success' => true,
            'data' => new ProprietarioResource($proprietario->fresh(['terreno', 'createdBy', 'updatedBy'])),
            'message' => 'Proprietário atualizado com sucesso!',
        ]);
    }

    /**
     * Excluir um proprietário.
     */
    public function destroy(Proprietario $proprietario): JsonResponse
    {
        Gate::authorize('delete', $proprietario);
        $terreno = $proprietario->terreno()->first();
        $proprietario->delete();

        if ($terreno) {
            $this->workflowService->syncReadiness($terreno, request()->user(), 'owner_deleted');
        }

        return response()->json([
            'success' => true,
            'message' => 'Proprietário removido com sucesso!',
        ]);
    }
}
