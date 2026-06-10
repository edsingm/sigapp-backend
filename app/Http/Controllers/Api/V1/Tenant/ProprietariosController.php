<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProprietarioRequest;
use App\Http\Requests\Tenant\UpdateProprietarioRequest;
use App\Http\Resources\Tenant\ProprietarioResource;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Terreno;
use App\Services\Tenant\LandWorkflowService;
use App\Services\Tenant\ProprietarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class ProprietariosController extends Controller
{
    public function __construct(
        protected ProprietarioService $proprietarioService,
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

        return Cache::tags(["tenant:{$tenantId}:proprietarios"])->remember($cacheKey, now()->addMinutes(30), function () use ($request, $tenantId) {
            $perPage = (int) ($request->input('per_page') ?? 10);
            $terrenoId = $request->input('terreno_id') ? (int) $request->input('terreno_id') : null;

            $paginator = $this->proprietarioService->list($tenantId, $perPage, $terrenoId);

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
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $proprietario = $this->proprietarioService->create($data);
        /** @var Terreno|null $terreno */
        $terreno = $proprietario->terreno()->first();

        if ($terreno !== null) {
            $this->workflowService->syncReadiness($terreno, $request->user(), 'owner_created');
        }

        return response()->json([
            'success' => true,
            'data' => new ProprietarioResource($proprietario),
            'message' => 'Proprietário criado com sucesso!',
        ], 201);
    }

    /**
     * Exibir os detalhes de um proprietário específico.
     */
    public function show(Proprietario $proprietario): JsonResponse
    {
        Gate::authorize('view', $proprietario);

        $proprietario = $this->proprietarioService->findWithRelations((int) $proprietario->getKey());

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
        $data['updated_by'] = $request->user()->id;

        $proprietario = $this->proprietarioService->update($proprietario, $data);
        /** @var Terreno|null $terreno */
        $terreno = $proprietario->terreno()->first();

        if ($terreno !== null) {
            $this->workflowService->syncReadiness($terreno, $request->user(), 'owner_updated');
        }

        return response()->json([
            'success' => true,
            'data' => new ProprietarioResource($proprietario),
            'message' => 'Proprietário atualizado com sucesso!',
        ]);
    }

    /**
     * Excluir um proprietário.
     */
    public function destroy(Proprietario $proprietario): JsonResponse
    {
        Gate::authorize('delete', $proprietario);

        /** @var Terreno|null $terreno */
        $terreno = $proprietario->terreno()->first();
        $this->proprietarioService->delete($proprietario);

        if ($terreno) {
            $this->workflowService->syncReadiness($terreno, request()->user(), 'owner_deleted');
        }

        return response()->json([
            'success' => true,
            'message' => 'Proprietário removido com sucesso!',
        ]);
    }
}
