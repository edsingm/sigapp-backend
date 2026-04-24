<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListContractsRequest;
use App\Http\Requests\Tenant\ShowContractRequest;
use App\Http\Requests\Tenant\SignContractRequest;
use App\Http\Requests\Tenant\StoreContractRequest;
use App\Http\Requests\Tenant\UpdateContractRequest;
use App\Http\Resources\Tenant\ContratoResource;
use App\Models\Tenant\Contrato;
use App\Services\ApiResponseService;
use App\Services\Tenant\LandWorkflowService;
use App\Services\Tenant\NegotiationService;
use Illuminate\Http\JsonResponse;

class ContractController extends Controller
{
    public function __construct(
        protected NegotiationService $service,
        protected LandWorkflowService $workflowService,
    ) {}

    /**
     * Listar contratos.
     */
    public function index(ListContractsRequest $request): JsonResponse
    {
        $result = $this->service->listContracts($request->validated());
        $result->through(
            fn (Contrato $contract) => (new ContratoResource($contract))->resolve()
        );

        return ApiResponseService::paginated($result, 'Contratos carregados com sucesso');
    }

    /**
     * Criar um novo contrato.
     */
    public function store(StoreContractRequest $request): JsonResponse
    {
        $contract = $this->service->createOrUpdateContract(null, $request->validated(), $request->user());

        return ApiResponseService::created(new ContratoResource($contract), 'Contrato criado com sucesso');
    }

    /**
     * Exibir os detalhes de um contrato específico.
     */
    public function show(ShowContractRequest $request, string $id): JsonResponse
    {
        $contract = $this->service->showContractById($id);

        return ApiResponseService::success(new ContratoResource($contract));
    }

    /**
     * Atualizar um contrato existente.
     */
    public function update(UpdateContractRequest $request, string $id): JsonResponse
    {
        $contract = $this->service->findContractOrFail($id);
        $updated = $this->service->createOrUpdateContract($contract, $request->validated(), $request->user());

        return ApiResponseService::success(new ContratoResource($updated), 'Contrato atualizado com sucesso');
    }

    /**
     * Registrar a assinatura de um contrato.
     */
    public function sign(SignContractRequest $request, string $id): JsonResponse
    {
        $contract = $this->service->findContractOrFail($id);
        $signed = $this->service->signContract($contract, $request->user(), $this->workflowService);

        return ApiResponseService::success(new ContratoResource($signed), 'Contrato assinado com sucesso');
    }
}
