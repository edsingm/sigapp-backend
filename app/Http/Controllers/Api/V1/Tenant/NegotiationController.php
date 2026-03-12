<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\NegociacaoResource;
use App\Models\Tenant\Negociacao;
use App\Services\ApiResponseService;
use App\Services\Tenant\LandWorkflowService;
use App\Services\Tenant\NegotiationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NegotiationController extends Controller
{
    public function __construct(
        protected NegotiationService $service,
        protected LandWorkflowService $workflowService,
    ) {
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Negociacao::class);

        $result = $this->service->listNegotiations($request->only(['status', 'search', 'per_page']));
        $result->setCollection(
            $result->getCollection()->map(fn (Negociacao $negociacao) => (new NegociacaoResource($negociacao))->resolve())
        );

        return ApiResponseService::paginated($result, 'Negociações carregadas com sucesso');
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Negociacao::class);

        $validated = $request->validate([
            'terreno_id' => ['required', 'integer', 'exists:terrenos,id'],
            'status' => ['nullable', 'string'],
            'proposal_value' => ['nullable', 'numeric'],
            'business_model' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $negociacao = $this->service->createNegotiation($validated, $request->user(), $this->workflowService);

        return ApiResponseService::created(new NegociacaoResource($negociacao), 'Negociação criada com sucesso');
    }

    public function show(string $id)
    {
        $negociacao = Negociacao::with(['terreno', 'eventos', 'contratos.partes'])->findOrFail($id);
        Gate::authorize('view', $negociacao);

        return ApiResponseService::success(new NegociacaoResource($negociacao));
    }

    public function update(Request $request, string $id)
    {
        $negociacao = Negociacao::findOrFail($id);
        Gate::authorize('update', $negociacao);

        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'proposal_value' => ['nullable', 'numeric'],
            'business_model' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->updateNegotiation($negociacao, $validated, $request->user(), $this->workflowService);

        return ApiResponseService::success(new NegociacaoResource($updated), 'Negociação atualizada com sucesso');
    }

    public function addEvent(Request $request, string $id)
    {
        $negociacao = Negociacao::findOrFail($id);
        Gate::authorize('update', $negociacao);

        $validated = $request->validate([
            'event_type' => ['required', 'string'],
            'payload_json' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'happened_at' => ['nullable', 'date'],
        ]);

        $event = $this->service->addEvent($negociacao, $validated, $request->user());

        return ApiResponseService::created($event, 'Evento da negociação registrado com sucesso');
    }
}
