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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class LegalizacaoController extends Controller
{
    public function __construct(protected LegalizacaoService $service) {}

    /**
     * Listar legalizações
     */
    public function index(ListLegalizacoesRequest $request): JsonResponse
    {
        $tenantId = tenant('id') ?? 'central';
        $filters = $request->validated();
        $cacheKey = "tenant:{$tenantId}:legalizacoes:index:".md5(json_encode($filters));

        $result = Cache::tags(["tenant:{$tenantId}:legalizacoes"])->remember($cacheKey, now()->addMinutes(30), function () use ($filters) {
            return $this->service->listar($filters);
        });

        return ApiResponseService::paginated($result);
    }

    /**
     * Criar nova legalização
     */
    public function store(StoreLegalizacaoRequest $request): JsonResponse
    {
        $legalizacao = $this->service->criar($request->validated(), $request->user());

        $tenantId = tenant('id') ?? 'central';
        Cache::tags(["tenant:{$tenantId}:legalizacoes"])->flush();

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
        $tenantId = tenant('id') ?? 'central';
        $cacheKey = "tenant:{$tenantId}:legalizacoes:show:{$id}";

        $result = Cache::tags(["tenant:{$tenantId}:legalizacoes"])->remember($cacheKey, now()->addMinutes(30), function () use ($id) {
            return $this->service->buscar((int) $id);
        });

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

        $tenantId = tenant('id') ?? 'central';
        Cache::tags(["tenant:{$tenantId}:legalizacoes"])->flush();

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

        $tenantId = tenant('id') ?? 'central';
        Cache::tags(["tenant:{$tenantId}:legalizacoes"])->flush();

        return ApiResponseService::noContent();
    }

    /**
     * Sincronizar Gantt (upsert em lote de etapas e dependências)
     */
    public function syncGantt(SyncGanttRequest $request, string $id): JsonResponse
    {
        $legalizacao = $this->service->findOrFail($id);
        $result = $this->service->syncGantt($legalizacao, $request->validated());

        $tenantId = tenant('id') ?? 'central';
        Cache::tags([
            "tenant:{$tenantId}:legalizacoes",
            "tenant:{$tenantId}:legalizacao_etapas",
            "tenant:{$tenantId}:legalizacao_dependencias",
        ])->flush();

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
        $tenantId = tenant('id') ?? 'central';
        $filters = $request->validated();
        $cacheKey = "tenant:{$tenantId}:legalizacoes:eligible-terrenos:".md5(json_encode($filters));

        $result = Cache::tags(["tenant:{$tenantId}:legalizacoes"])->remember($cacheKey, now()->addMinutes(30), function () use ($filters) {
            return $this->service->listarTerrenosElegiveis($filters);
        });

        return ApiResponseService::paginated($result);
    }

    /**
     * Recalcular progresso da legalização
     */
    public function recalcularProgresso(RecalculateLegalizacaoProgressRequest $request, string $id): JsonResponse
    {
        $legalizacao = $this->service->recalcularProgresso($this->service->findOrFail($id));

        $tenantId = tenant('id') ?? 'central';
        Cache::tags(["tenant:{$tenantId}:legalizacoes"])->flush();

        return ApiResponseService::success(
            new LegalizacaoResource($legalizacao),
            'Progresso recalculado com sucesso'
        );
    }
}
