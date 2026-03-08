<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Services\ApiResponseService;
use App\Services\Tenant\LegalizacaoService;
use App\Services\Tenant\MobilePushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use App\Http\Requests\Tenant\StoreLegalizacaoEtapaRequest;
use App\Http\Requests\Tenant\UpdateLegalizacaoEtapaRequest;
use App\Http\Resources\Tenant\LegalizacaoEtapaResource;

class LegalizacaoEtapaController extends Controller
{
    protected LegalizacaoService $service;

    public function __construct(
        LegalizacaoService $service,
        protected MobilePushService $mobilePushService
    )
    {
        $this->service = $service;
    }

    /**
     * Listar etapas de uma legalização
     */
    public function index(Request $request, string $legalizacaoId)
    {
        Gate::authorize('viewAny', LegalizacaoEtapa::class);

        try {
            $legalizacao = Legalizacao::findOrFail($legalizacaoId);
            
            $tenantId = tenant('id') ?? 'central';
            $cacheKey = "tenant:{$tenantId}:legalizacoes:etapas:{$legalizacaoId}";
            
            $etapas = \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:legalizacao_etapas"])->remember($cacheKey, now()->addMinutes(30), function () use ($legalizacao) {
                return $legalizacao->etapas()->orderBy('ordem')->get();
            });

            return ApiResponseService::success(
                LegalizacaoEtapaResource::collection($etapas)
            );
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao listar etapas', [
                'legalizacao_id' => $legalizacaoId,
                'error' => $e->getMessage()
            ]);

            return ApiResponseService::serverError('Erro ao listar etapas');
        }
    }

    /**
     * Criar nova etapa
     */
    public function store(StoreLegalizacaoEtapaRequest $request, string $legalizacaoId)
    {
        Gate::authorize('create', LegalizacaoEtapa::class);

        try {
            $legalizacao = Legalizacao::findOrFail($legalizacaoId);
            
            $dados = $request->validated();
            $dados['legalizacao_id'] = $legalizacao->id;
            $dados['created_by'] = $request->user()->id;
            $dados['updated_by'] = $request->user()->id;

            if (!isset($dados['ordem'])) {
                $dados['ordem'] = $this->service->proximaOrdem($legalizacao->id);
            }

            $etapa = LegalizacaoEtapa::create($dados);

            $legalizacao->recalcularProgresso();

            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:legalizacao_etapas", "tenant:{$tenantId}:legalizacoes"])->flush();

            return ApiResponseService::created(
                new LegalizacaoEtapaResource($etapa),
                'Etapa criada com sucesso'
            );
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao criar etapa', [
                'legalizacao_id' => $legalizacaoId,
                'error' => $e->getMessage()
            ]);

            return ApiResponseService::serverError('Erro ao criar etapa');
        }
    }

    /**
     * Buscar etapa por ID
     */
    public function show(string $legalizacaoId, string $id)
    {
        try {
            $etapa = LegalizacaoEtapa::where('legalizacao_id', $legalizacaoId)
                ->with(['dependenciasDestino', 'dependenciasOrigem'])
                ->findOrFail($id);
            
            Gate::authorize('view', $etapa);

            return ApiResponseService::success(
                new LegalizacaoEtapaResource($etapa)
            );
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao buscar etapa', [
                'legalizacao_id' => $legalizacaoId,
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return ApiResponseService::notFound('Etapa não encontrada');
        }
    }

    /**
     * Atualizar etapa
     */
    public function update(UpdateLegalizacaoEtapaRequest $request, string $legalizacaoId, string $id)
    {
        try {
            $etapa = LegalizacaoEtapa::where('legalizacao_id', $legalizacaoId)
                ->findOrFail($id);
            
            Gate::authorize('update', $etapa);

            $dados = $request->validated();
            $dados['updated_by'] = $request->user()->id;

            $etapa->update($dados);

            $legalizacao = $etapa->legalizacao;
            $legalizacao->recalcularProgresso();

            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:legalizacao_etapas", "tenant:{$tenantId}:legalizacoes"])->flush();

            return ApiResponseService::success(
                new LegalizacaoEtapaResource($etapa->fresh()),
                'Etapa atualizada com sucesso'
            );
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao atualizar etapa', [
                'legalizacao_id' => $legalizacaoId,
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return ApiResponseService::serverError('Erro ao atualizar etapa');
        }
    }

    /**
     * Excluir etapa
     */
    public function destroy(string $legalizacaoId, string $id)
    {
        try {
            $etapa = LegalizacaoEtapa::where('legalizacao_id', $legalizacaoId)
                ->findOrFail($id);
            
            Gate::authorize('delete', $etapa);

            $legalizacao = $etapa->legalizacao;
            
            $etapa->delete();

            $legalizacao->recalcularProgresso();

            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:legalizacao_etapas", "tenant:{$tenantId}:legalizacoes"])->flush();

            return ApiResponseService::noContent();
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao excluir etapa', [
                'legalizacao_id' => $legalizacaoId,
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return ApiResponseService::serverError('Erro ao excluir etapa');
        }
    }

    /**
     * Reordenar etapas
     */
    public function reorder(Request $request, string $legalizacaoId)
    {
        Gate::authorize('reorder', LegalizacaoEtapa::class);

        try {
            $legalizacao = Legalizacao::findOrFail($legalizacaoId);

            $request->validate([
                'etapas' => 'required|array',
                'etapas.*.id' => 'required|integer|exists:legalizacao_etapas,id',
                'etapas.*.ordem' => 'required|integer|min:1',
            ]);

            $this->service->reordenarEtapas($legalizacao, $request->input('etapas'));

            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:legalizacao_etapas", "tenant:{$tenantId}:legalizacoes"])->flush();

            return ApiResponseService::success(
                LegalizacaoEtapaResource::collection($legalizacao->etapas()->orderBy('ordem')->get()),
                'Etapas reordenadas com sucesso'
            );
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao reordenar etapas', [
                'legalizacao_id' => $legalizacaoId,
                'error' => $e->getMessage()
            ]);

            return ApiResponseService::serverError('Erro ao reordenar etapas');
        }
    }

    /**
     * Atualizar status da etapa
     */
    public function updateStatus(Request $request, string $legalizacaoId, string $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:pendente,em_andamento,concluida,bloqueada,atrasada,nao_iniciada,cancelada',
            ]);

            $etapa = LegalizacaoEtapa::where('legalizacao_id', $legalizacaoId)
                ->findOrFail($id);

            Gate::authorize('update', $etapa);

            $status = $this->normalizarStatus((string) $request->input('status'));
            $etapa = $this->service->atualizarStatusEtapa($etapa, $status)->loadMissing('legalizacao.terreno');
            $this->mobilePushService->notifyAllUsers(
                [
                    'title' => 'Etapa de legalização atualizada',
                    'body' => "A etapa {$etapa->nome} foi atualizada para {$status}.",
                    'type' => 'legalizacao.etapa.status_atualizado',
                    'entity_type' => 'legalizacao_etapa',
                    'entity_id' => (string) $etapa->id,
                    'target_route' => $etapa->legalizacao?->terreno_id
                        ? "/terrenos/{$etapa->legalizacao->terreno_id}"
                        : '/notifications',
                    'payload' => [
                        'tenant_slug' => tenant('slug'),
                        'legalizacao_id' => $etapa->legalizacao_id,
                        'etapa_id' => $etapa->id,
                    ],
                ],
                $request->user()
            );

            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:legalizacao_etapas", "tenant:{$tenantId}:legalizacoes"])->flush();

            return ApiResponseService::success(
                new LegalizacaoEtapaResource($etapa),
                'Status atualizado com sucesso'
            );
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                throw $e;
            }

            Log::error('Erro ao atualizar status', [
                'legalizacao_id' => $legalizacaoId,
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return ApiResponseService::serverError('Erro ao atualizar status');
        }
    }

    protected function normalizarStatus(string $status): string
    {
        return match ($status) {
            'nao_iniciada' => LegalizacaoEtapa::STATUS_PENDENTE,
            'cancelada' => LegalizacaoEtapa::STATUS_BLOQUEADA,
            default => $status,
        };
    }
}
