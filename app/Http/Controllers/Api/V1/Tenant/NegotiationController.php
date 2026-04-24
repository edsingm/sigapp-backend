<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListNegotiationsRequest;
use App\Http\Requests\Tenant\ShowNegotiationRequest;
use App\Http\Requests\Tenant\StoreNegotiationEventRequest;
use App\Http\Requests\Tenant\StoreNegotiationRequest;
use App\Http\Requests\Tenant\UpdateNegotiationRequest;
use App\Http\Resources\Tenant\NegociacaoEventoResource;
use App\Http\Resources\Tenant\NegociacaoResource;
use App\Models\Tenant\Negociacao;
use App\Services\ApiResponseService;
use App\Services\Tenant\NegotiationService;
use Illuminate\Http\JsonResponse;

class NegotiationController extends Controller
{
    public function __construct(
        protected NegotiationService $service,
    ) {}

    /**
     * Listar negociações.
     */
    public function index(ListNegotiationsRequest $request): JsonResponse
    {
        $result = $this->service->listNegotiations($request->validated());
        $result->through(
            fn (Negociacao $negociacao) => (new NegociacaoResource($negociacao))->resolve()
        );

        return ApiResponseService::paginated($result, 'Negociações carregadas com sucesso');
    }

    /**
     * Criar uma nova negociação.
     */
    public function store(StoreNegotiationRequest $request): JsonResponse
    {
        $negociacao = $this->service->createNegotiation($request->validated(), $request->user());

        return ApiResponseService::created(new NegociacaoResource($negociacao), 'Negociação criada com sucesso');
    }

    /**
     * Exibir os detalhes de uma negociação específica.
     */
    public function show(ShowNegotiationRequest $request, string $id): JsonResponse
    {
        $negociacao = $this->service->showById($id);

        return ApiResponseService::success(new NegociacaoResource($negociacao));
    }

    /**
     * Atualizar uma negociação existente.
     */
    public function update(UpdateNegotiationRequest $request, string $id): JsonResponse
    {
        $negociacao = $this->service->findOrFail($id);
        $updated = $this->service->updateNegotiation($negociacao, $request->validated(), $request->user());

        return ApiResponseService::success(new NegociacaoResource($updated), 'Negociação atualizada com sucesso');
    }

    /**
     * Adicionar um evento a uma negociação.
     */
    public function addEvent(StoreNegotiationEventRequest $request, string $id): JsonResponse
    {
        $negociacao = $this->service->findOrFail($id);
        $event = $this->service->addEvent($negociacao, $request->validated(), $request->user());

        return ApiResponseService::created(new NegociacaoEventoResource($event), 'Evento da negociação registrado com sucesso');
    }
}
