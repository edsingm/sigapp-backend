<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProprietarioRequest;
use App\Http\Requests\Tenant\UpdateProprietarioRequest;
use App\Http\Resources\Tenant\ProprietarioResource;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Terreno;
use App\Services\ApiResponseService;
use App\Services\Tenant\LandWorkflowService;
use App\Services\Tenant\ProprietarioService;
use App\Services\Tenant\TenantCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProprietariosController extends Controller
{
    public function __construct(
        protected ProprietarioService $proprietarioService,
        protected LandWorkflowService $workflowService,
        private readonly TenantCacheService $cache,
    ) {}

    /**
     * Listar proprietários.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Proprietario::class);

        $filters = $request->only(['per_page', 'page', 'terreno_id']);
        $cacheKey = $this->cache->key('proprietarios', 'index', $filters);
        $tenantId = (int) (tenant('id') ?? 0);
        $perPage = min(max((int) ($request->input('per_page') ?? 10), 1), 100);
        $terrenoId = $request->input('terreno_id') ? (int) $request->input('terreno_id') : null;

        $paginator = $this->cache->remember(
            'proprietarios',
            $cacheKey,
            now()->addMinutes(30),
            fn () => $this->proprietarioService->list($tenantId, $perPage, $terrenoId),
        );

        return $this->respondWithPagination($paginator, ProprietarioResource::class);
    }

    /**
     * Listar proprietários em formato enxuto para selects (id + nome).
     */
    public function proprietariosForSelect(): JsonResponse
    {
        Gate::authorize('viewAny', Proprietario::class);

        $proprietarios = $this->proprietarioService->forSelect()
            ->map(static fn (Proprietario $proprietario): array => [
                'id' => $proprietario->getKey(),
                'nome' => $proprietario->getAttribute('nome'),
            ])->values();

        return ApiResponseService::success($proprietarios, 'Proprietários recuperados com sucesso');
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

        return ApiResponseService::created(
            new ProprietarioResource($proprietario),
            'Proprietário criado com sucesso!'
        );
    }

    /**
     * Exibir os detalhes de um proprietário específico.
     */
    public function show(Proprietario $proprietario): JsonResponse
    {
        Gate::authorize('view', $proprietario);

        $proprietario = $this->proprietarioService->findWithRelations((int) $proprietario->getKey());

        return ApiResponseService::success(new ProprietarioResource($proprietario));
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

        return ApiResponseService::success(
            new ProprietarioResource($proprietario),
            'Proprietário atualizado com sucesso!'
        );
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

        return ApiResponseService::success(null, 'Proprietário removido com sucesso!');
    }
}
