<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\ContratoResource;
use App\Models\Tenant\Contrato;
use App\Services\ApiResponseService;
use App\Services\Tenant\LandWorkflowService;
use App\Services\Tenant\NegotiationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContractController extends Controller
{
    public function __construct(
        protected NegotiationService $service,
        protected LandWorkflowService $workflowService,
    ) {
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Contrato::class);

        $query = Contrato::query()->with(['terreno', 'negociacao', 'partes']);
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where('contract_number', 'like', "%{$search}%");
        }

        $result = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 10));
        $result->setCollection(
            $result->getCollection()->map(fn (Contrato $contract) => (new ContratoResource($contract))->resolve())
        );

        return ApiResponseService::paginated($result, 'Contratos carregados com sucesso');
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Contrato::class);

        $validated = $this->validatedPayload($request);
        $contract = $this->service->createOrUpdateContract(null, $validated, $request->user());

        return ApiResponseService::created(new ContratoResource($contract), 'Contrato criado com sucesso');
    }

    public function show(string $id)
    {
        $contract = Contrato::with(['terreno', 'negociacao', 'partes'])->findOrFail($id);
        Gate::authorize('view', $contract);

        return ApiResponseService::success(new ContratoResource($contract));
    }

    public function update(Request $request, string $id)
    {
        $contract = Contrato::with('partes')->findOrFail($id);
        Gate::authorize('update', $contract);

        $validated = $this->validatedPayload($request);
        $updated = $this->service->createOrUpdateContract($contract, $validated, $request->user());

        return ApiResponseService::success(new ContratoResource($updated), 'Contrato atualizado com sucesso');
    }

    public function sign(Request $request, string $id)
    {
        $contract = Contrato::with('partes')->findOrFail($id);
        Gate::authorize('update', $contract);

        $signed = $this->service->signContract($contract, $request->user(), $this->workflowService);

        return ApiResponseService::success(new ContratoResource($signed), 'Contrato assinado com sucesso');
    }

    protected function validatedPayload(Request $request): array
    {
        return $request->validate([
            'terreno_id' => ['required', 'integer', 'exists:terrenos,id'],
            'negociacao_id' => ['nullable', 'integer', 'exists:negociacoes,id'],
            'contract_type' => ['nullable', 'string'],
            'contract_number' => ['nullable', 'string'],
            'signed_at' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
            'file_path' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'partes' => ['nullable', 'array'],
            'partes.*.name' => ['required_with:partes', 'string'],
            'partes.*.document' => ['nullable', 'string'],
            'partes.*.party_type' => ['nullable', 'string'],
            'partes.*.signer_name' => ['nullable', 'string'],
            'partes.*.signer_document' => ['nullable', 'string'],
        ]);
    }
}
