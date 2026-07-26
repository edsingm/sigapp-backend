<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyLegalizacaoRequest;
use App\Http\Requests\Tenant\EligibleLegalizacaoTerrenosRequest;
use App\Http\Requests\Tenant\ListLegalizacoesRequest;
use App\Http\Requests\Tenant\RecalculateLegalizacaoProgressRequest;
use App\Http\Requests\Tenant\ShowLegalizacaoRequest;
use App\Http\Requests\Tenant\StoreLegalizacaoRequest;
use App\Http\Requests\Tenant\SyncGanttRequest;
use App\Http\Requests\Tenant\UpdateLegalizacaoRequest;
use App\Http\Resources\Tenant\LegalizacaoDependenciaResource;
use App\Http\Resources\Tenant\LegalizacaoEtapaResource;
use App\Http\Resources\Tenant\LegalizacaoResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\LegalizacaoService;
use App\Services\Tenant\TenantCacheService;
use Illuminate\Http\JsonResponse;

class LegalizacaoController extends Controller
{
    public function __construct(
        protected LegalizacaoService $service,
        private readonly TenantCacheService $cache,
    ) {}

    /**
     * Listar legalizações
     */
    public function index(ListLegalizacoesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $cacheKey = $this->cache->key('legalizacoes', 'index', $filters);

        $result = $this->cache->remember(
            'legalizacoes',
            $cacheKey,
            now()->addMinutes(30),
            fn () => $this->service->listar($filters),
        );

        return ApiResponseService::paginated($result);
    }

    /**
     * Criar nova legalização
     */
    public function store(StoreLegalizacaoRequest $request): JsonResponse
    {
        $legalizacao = $this->service->criar($request->validated(), $request->user());

        return ApiResponseService::created(
            new LegalizacaoResource($legalizacao),
            'Legalização criada com sucesso'
        );
    }

    /**
     * Buscar legalização por ID
     */
    public function show(ShowLegalizacaoRequest $request, string $id): JsonResponse
    {
        $cacheKey = $this->cache->key('legalizacoes', 'show', ['id' => $id]);

        $result = $this->cache->remember(
            'legalizacoes',
            $cacheKey,
            now()->addMinutes(30),
            fn () => $this->service->buscar((int) $id),
        );

        return ApiResponseService::success([
            'legalizacao' => new LegalizacaoResource($result['legalizacao']),
            'etapas' => LegalizacaoEtapaResource::collection($result['etapas']),
            'dependencias' => LegalizacaoDependenciaResource::collection($result['dependencias']),
        ]);
    }

    /**
     * Atualizar legalização
     */
    public function update(UpdateLegalizacaoRequest $request, string $id): JsonResponse
    {
        $legalizacao = $this->service->findOrFail($id);
        $legalizacao = $this->service->atualizar($legalizacao, $request->validated(), $request->user());

        return ApiResponseService::success(
            new LegalizacaoResource($legalizacao),
            'Legalização atualizada com sucesso'
        );
    }

    /**
     * Excluir legalização
     */
    public function destroy(DestroyLegalizacaoRequest $request, string $id): JsonResponse
    {
        $legalizacao = $this->service->findOrFail($id);
        $this->service->excluir($legalizacao);

        return ApiResponseService::noContent();
    }

    /**
     * Sincronizar Gantt (upsert em lote de etapas e dependências)
     */
    public function syncGantt(SyncGanttRequest $request, string $id): JsonResponse
    {
        $legalizacao = $this->service->findOrFail($id);
        $result = $this->service->syncGantt($legalizacao, $request->validated());

        $this->cache->flushModules('legalizacoes', 'legalizacao_etapas', 'legalizacao_dependencias');

        return ApiResponseService::success([
            'legalizacao' => new LegalizacaoResource($result['legalizacao']),
            'etapas' => LegalizacaoEtapaResource::collection($result['etapas']),
            'dependencias' => LegalizacaoDependenciaResource::collection($result['dependencias']),
        ], 'Gantt sincronizado com sucesso');
    }

    /**
     * Listar terrenos elegíveis (status "Opção" e sem legalização)
     */
    public function eligibleTerrenos(EligibleLegalizacaoTerrenosRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $cacheKey = $this->cache->key('legalizacoes', 'eligible-terrenos', $filters);

        $result = $this->cache->remember(
            'legalizacoes',
            $cacheKey,
            now()->addMinutes(30),
            fn () => $this->service->listarTerrenosElegiveis($filters),
        );

        return ApiResponseService::paginated($result);
    }

    /**
     * Recalcular progresso da legalização
     */
    public function recalcularProgresso(RecalculateLegalizacaoProgressRequest $request, string $id): JsonResponse
    {
        $legalizacao = $this->service->recalcularProgresso($this->service->findOrFail($id));

        return ApiResponseService::success(
            new LegalizacaoResource($legalizacao),
            'Progresso recalculado com sucesso'
        );
    }
}
