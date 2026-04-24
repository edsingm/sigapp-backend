<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyLegalizacaoEtapaRequest;
use App\Http\Requests\Tenant\ListLegalizacaoEtapasRequest;
use App\Http\Requests\Tenant\ReorderEtapasRequest;
use App\Http\Requests\Tenant\ShowLegalizacaoEtapaRequest;
use App\Http\Requests\Tenant\StoreLegalizacaoEtapaRequest;
use App\Http\Requests\Tenant\UpdateLegalizacaoEtapaRequest;
use App\Http\Requests\Tenant\UpdateStatusEtapaRequest;
use App\Http\Resources\Tenant\LegalizacaoEtapaResource;
use App\Repositories\Tenant\LegalizacaoEtapaRepository;
use App\Services\ApiResponseService;
use App\Services\Tenant\LegalizacaoService;
use App\Services\Tenant\MobilePushService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LegalizacaoEtapaController extends Controller
{
    public function __construct(
        protected LegalizacaoService $legalizacaoService,
        protected LegalizacaoEtapaRepository $etapaRepository,
        protected MobilePushService $mobilePushService,
    ) {}

    /**
     * Listar etapas de uma legalização
     */
    public function index(ListLegalizacaoEtapasRequest $request, string $legalizacaoId): JsonResponse|AnonymousResourceCollection
    {
        try {
            $legalizacao = $this->legalizacaoService->findOrFail($legalizacaoId);
            $etapas = $this->etapaRepository->findByLegalizacao(
                $legalizacao->id,
                ['dependenciasDestino', 'dependenciasOrigem']
            );

            return ApiResponseService::success(
                LegalizacaoEtapaResource::collection($etapas)
            );
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResponseService::serverError('Erro ao listar etapas');
        }
    }

    /**
     * Criar nova etapa
     */
    public function store(StoreLegalizacaoEtapaRequest $request, string $legalizacaoId): JsonResponse
    {
        try {
            $legalizacao = $this->legalizacaoService->findOrFail($legalizacaoId);
            $dados = $request->validated();
            $dados['legalizacao_id'] = $legalizacao->id;
            $dados['created_by'] = $request->user()->id;
            $dados['updated_by'] = $request->user()->id;

            if (! isset($dados['ordem'])) {
                $dados['ordem'] = $this->legalizacaoService->proximaOrdem($legalizacao->id);
            }

            $etapa = $this->legalizacaoService->adicionarEtapa($legalizacao, $dados);

            return ApiResponseService::created(
                new LegalizacaoEtapaResource($etapa),
                'Etapa criada com sucesso'
            );
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResponseService::serverError('Erro ao criar etapa');
        }
    }

    /**
     * Buscar etapa por ID
     */
    public function show(ShowLegalizacaoEtapaRequest $request, string $legalizacaoId, string $id): JsonResponse
    {
        try {
            $etapa = $this->etapaRepository->findByIdAndLegalizacao(
                $id,
                $legalizacaoId,
                ['dependenciasDestino', 'dependenciasOrigem']
            );

            return ApiResponseService::success(
                new LegalizacaoEtapaResource($etapa)
            );
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResponseService::notFound('Etapa não encontrada');
        }
    }

    /**
     * Atualizar etapa
     */
    public function update(UpdateLegalizacaoEtapaRequest $request, string $legalizacaoId, string $id): JsonResponse
    {
        try {
            $etapa = $this->etapaRepository->findByIdAndLegalizacao($id, $legalizacaoId);
            $dados = $request->validated();
            $dados['updated_by'] = $request->user()->id;

            $etapa = $this->legalizacaoService->atualizarEtapa($etapa, $dados);

            return ApiResponseService::success(
                new LegalizacaoEtapaResource($etapa),
                'Etapa atualizada com sucesso'
            );
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResponseService::serverError('Erro ao atualizar etapa');
        }
    }

    /**
     * Excluir etapa
     */
    public function destroy(DestroyLegalizacaoEtapaRequest $request, string $legalizacaoId, string $id): JsonResponse
    {
        try {
            $etapa = $this->etapaRepository->findByIdAndLegalizacao($id, $legalizacaoId);
            $this->legalizacaoService->removerEtapa($etapa);

            return ApiResponseService::noContent();
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResponseService::serverError('Erro ao excluir etapa');
        }
    }

    /**
     * Reordenar etapas
     */
    public function reorder(ReorderEtapasRequest $request, string $legalizacaoId): JsonResponse
    {
        try {
            $legalizacao = $this->legalizacaoService->findOrFail($legalizacaoId);
            $this->legalizacaoService->reordenarEtapas($legalizacao, $request->validated()['etapas']);

            $etapas = $this->etapaRepository->findByLegalizacao($legalizacao->id);

            return ApiResponseService::success(
                LegalizacaoEtapaResource::collection($etapas),
                'Etapas reordenadas com sucesso'
            );
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResponseService::serverError('Erro ao reordenar etapas');
        }
    }

    /**
     * Atualizar status da etapa
     */
    public function updateStatus(UpdateStatusEtapaRequest $request, string $legalizacaoId, string $id): JsonResponse
    {
        try {
            $etapa = $this->etapaRepository->findByIdAndLegalizacao($id, $legalizacaoId);
            $status = $this->normalizarStatus($request->validated()['status']);
            $etapa = $this->legalizacaoService->atualizarStatusEtapa($etapa, $status);

            $this->mobilePushService->notifyAllUsers(
                [
                    'title' => 'Etapa de legalização atualizada',
                    'body' => "A etapa {$etapa->titulo} foi atualizada para {$status}.",
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

            return ApiResponseService::success(
                new LegalizacaoEtapaResource($etapa),
                'Status atualizado com sucesso'
            );
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResponseService::serverError('Erro ao atualizar status');
        }
    }

    private function normalizarStatus(string $status): string
    {
        return match ($status) {
            'nao_iniciada' => 'Pendente',
            'cancelada' => 'Bloqueada',
            default => $status,
        };
    }
}
