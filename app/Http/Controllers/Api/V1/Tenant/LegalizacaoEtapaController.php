<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Events\Tenant\LegalizacaoEtapaStatusUpdated;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LegalizacaoEtapaController extends Controller
{
    public function __construct(
        protected LegalizacaoService $legalizacaoService,
        protected LegalizacaoEtapaRepository $etapaRepository,
    ) {}

    /**
     * Listar etapas de uma legalização
     */
    public function index(ListLegalizacaoEtapasRequest $request, string $legalizacaoId): JsonResponse|AnonymousResourceCollection
    {
        try {
            $legalizacao = $this->legalizacaoService->findOrFail($legalizacaoId);
            $etapas = $this->etapaRepository->findByLegalizacao(
                (string) $legalizacao->getKey(),
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
            $dados['legalizacao_id'] = $legalizacao->getKey();
            $dados['created_by'] = $request->user()->id;
            $dados['updated_by'] = $request->user()->id;

            if (! isset($dados['ordem'])) {
                $dados['ordem'] = $this->legalizacaoService->proximaOrdem((int) $legalizacao->getKey());
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

            $etapas = $this->etapaRepository->findByLegalizacao((string) $legalizacao->getKey());

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

            LegalizacaoEtapaStatusUpdated::dispatch($etapa, $status, $request->user());

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
