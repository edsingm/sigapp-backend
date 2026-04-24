<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\FinalizeCommitteeDecisionRequest;
use App\Http\Requests\Tenant\ListCommitteeReviewsRequest;
use App\Http\Requests\Tenant\ShowCommitteeReviewRequest;
use App\Http\Requests\Tenant\StoreCommitteeReviewRequest;
use App\Http\Requests\Tenant\UpsertCommitteeDepartmentReviewRequest;
use App\Http\Resources\Tenant\ComiteRevisaoResource;
use App\Models\Tenant\ComiteRevisao;
use App\Services\ApiResponseService;
use App\Services\Tenant\CommitteeService;

class CommitteeController extends Controller
{
    public function __construct(
        private readonly CommitteeService $service,
    ) {}

    /**
     * Listar revisões de comitê.
     */
    public function index(ListCommitteeReviewsRequest $request)
    {
        $result = $this->service->list($request->validated());
        $result->through(
            fn (ComiteRevisao $review): array => (new ComiteRevisaoResource($review))->resolve()
        );

        return ApiResponseService::paginated($result, 'Revisões de comitê carregadas com sucesso');
    }

    /**
     * Criar uma nova revisão de comitê.
     */
    public function store(StoreCommitteeReviewRequest $request)
    {
        $review = $this->service->create($request->validated(), $request->user());

        return ApiResponseService::created(new ComiteRevisaoResource($review), 'Comitê criado com sucesso');
    }

    /**
     * Exibir os detalhes de uma revisão de comitê específica.
     */
    public function show(ShowCommitteeReviewRequest $request, string $id)
    {
        return ApiResponseService::success(
            new ComiteRevisaoResource($this->service->showById($id))
        );
    }

    /**
     * Criar ou atualizar o parecer de um departamento.
     */
    public function upsertDepartmentReview(UpsertCommitteeDepartmentReviewRequest $request, string $id)
    {
        $updated = $this->service->upsertDepartmentReview(
            $this->service->findOrFail($id),
            $request->validated(),
            $request->user(),
        );

        return ApiResponseService::success(new ComiteRevisaoResource($updated), 'Parecer registrado com sucesso');
    }

    /**
     * Finalizar a decisão do comitê.
     */
    public function finalize(FinalizeCommitteeDecisionRequest $request, string $id)
    {
        $updated = $this->service->finalize(
            $this->service->findOrFail($id),
            $request->validated(),
            $request->user(),
        );

        return ApiResponseService::success(new ComiteRevisaoResource($updated), 'Decisão de comitê registrada com sucesso');
    }
}
