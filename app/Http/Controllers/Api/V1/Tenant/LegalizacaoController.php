<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Legalizacao;
use App\Services\ApiResponseService;
use App\Services\Tenant\LegalizacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use App\Http\Requests\Tenant\StoreLegalizacaoRequest;
use App\Http\Requests\Tenant\UpdateLegalizacaoRequest;
use App\Http\Requests\Tenant\SyncGanttRequest;
use App\Http\Resources\tenant\LegalizacaoResource;
use App\Http\Resources\tenant\LegalizacaoEtapaResource;
use App\Http\Resources\tenant\LegalizacaoDependenciaResource;

class LegalizacaoController extends Controller
{
    protected LegalizacaoService $service;

    public function __construct(LegalizacaoService $service)
    {
        $this->service = $service;
    }

    /**
     * Listar legalizações
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Legalizacao::class);

        try {
            $tenantId = tenant('id') ?? 'central';
            $filters = $request->only(['search', 'terreno_id', 'status', 'per_page', 'page']);
            
            $cacheKey = "tenant:{$tenantId}:legalizacoes:index:" . md5(json_encode($filters));
            
            $result = \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:legalizacoes"])->remember($cacheKey, now()->addMinutes(30), function () use ($filters) {
                return $this->service->listar($filters);
            });

            return ApiResponseService::paginated($result);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao listar legalizações', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ApiResponseService::serverError('Erro ao listar legalizações');
        }
    }

    /**
     * Criar nova legalização
     */
    public function store(StoreLegalizacaoRequest $request)
    {
        Gate::authorize('create', Legalizacao::class);

        try {
            $legalizacao = $this->service->criar($request->validated());

            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:legalizacoes"])->flush();

            return ApiResponseService::created(
                new LegalizacaoResource($legalizacao),
                'Legalização criada com sucesso'
            );
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao criar legalização', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return ApiResponseService::error(
                'CREATE_ERROR',
                $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * Buscar legalização por ID
     */
    public function show(string $id)
    {
        try {
            $legalizacao = Legalizacao::findOrFail($id);
            Gate::authorize('view', $legalizacao);

            $tenantId = tenant('id') ?? 'central';
            $cacheKey = "tenant:{$tenantId}:legalizacoes:show:{$id}";
            
            $result = \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:legalizacoes"])->remember($cacheKey, now()->addMinutes(30), function () use ($legalizacao) {
                return $this->service->buscar($legalizacao->id);
            });

            return ApiResponseService::success([
                'legalizacao' => new LegalizacaoResource($result['legalizacao']),
                'etapas' => LegalizacaoEtapaResource::collection($result['etapas']),
                'dependencias' => LegalizacaoDependenciaResource::collection($result['dependencias']),
            ]);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao buscar legalização', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return ApiResponseService::notFound('Legalização não encontrada');
        }
    }

    /**
     * Atualizar legalização
     */
    public function update(UpdateLegalizacaoRequest $request, string $id)
    {
        try {
            $legalizacao = Legalizacao::findOrFail($id);
            Gate::authorize('update', $legalizacao);

            $legalizacao = $this->service->atualizar($legalizacao, $request->validated());

            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:legalizacoes"])->flush();

            return ApiResponseService::success(
                new LegalizacaoResource($legalizacao),
                'Legalização atualizada com sucesso'
            );
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao atualizar legalização', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return ApiResponseService::serverError('Erro ao atualizar legalização');
        }
    }

    /**
     * Excluir legalização
     */
    public function destroy(string $id)
    {
        try {
            $legalizacao = Legalizacao::findOrFail($id);
            Gate::authorize('delete', $legalizacao);

            $this->service->excluir($legalizacao->id);

            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:legalizacoes"])->flush();

            return ApiResponseService::noContent();
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao excluir legalização', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return ApiResponseService::serverError('Erro ao excluir legalização');
        }
    }

    /**
     * Sincronizar Gantt (upsert em lote de etapas e dependências)
     */
    public function syncGantt(SyncGanttRequest $request, string $id)
    {
        try {
            $legalizacao = Legalizacao::findOrFail($id);
            Gate::authorize('syncGantt', $legalizacao);

            $result = $this->service->syncGantt($legalizacao, $request->validated());

            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags([
                "tenant:{$tenantId}:legalizacoes",
                "tenant:{$tenantId}:legalizacao_etapas",
                "tenant:{$tenantId}:legalizacao_dependencias"
            ])->flush();

            return ApiResponseService::success([
                'legalizacao' => new LegalizacaoResource($result['legalizacao']),
                'etapas' => LegalizacaoEtapaResource::collection($result['etapas']),
                'dependencias' => LegalizacaoDependenciaResource::collection($result['dependencias']),
            ], 'Gantt sincronizado com sucesso');
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao sincronizar gantt', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ApiResponseService::error(
                'SYNC_ERROR',
                $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * Listar terrenos elegíveis (status "Opção" e sem legalização)
     */
    public function eligibleTerrenos(Request $request)
    {
        try {
            $tenantId = tenant('id') ?? 'central';
            $filters = $request->only(['search', 'per_page', 'page']);
            
            $cacheKey = "tenant:{$tenantId}:legalizacoes:eligible-terrenos:" . md5(json_encode($filters));
            
            $result = \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:legalizacoes"])->remember($cacheKey, now()->addMinutes(30), function () use ($filters) {
                return $this->service->listarTerrenosElegiveis($filters);
            });

            return ApiResponseService::paginated($result);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao listar terrenos elegíveis', [
                'error' => $e->getMessage()
            ]);

            return ApiResponseService::serverError('Erro ao listar terrenos elegíveis');
        }
    }

    /**
     * Recalcular progresso da legalização
     */
    public function recalcularProgresso(string $id)
    {
        try {
            $legalizacao = Legalizacao::findOrFail($id);
            Gate::authorize('recalcularProgresso', $legalizacao);

            $legalizacao->recalcularProgresso();

            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:legalizacoes"])->flush();

            return ApiResponseService::success(
                new LegalizacaoResource($legalizacao),
                'Progresso recalculado com sucesso'
            );
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao recalcular progresso', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return ApiResponseService::serverError('Erro ao recalcular progresso');
        }
    }
}
