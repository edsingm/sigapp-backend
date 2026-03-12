<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\ComiteRevisaoResource;
use App\Models\Tenant\ComiteRevisao;
use App\Services\ApiResponseService;
use App\Services\Tenant\CommitteeService;
use App\Services\Tenant\LandWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommitteeController extends Controller
{
    public function __construct(
        protected CommitteeService $service,
        protected LandWorkflowService $workflowService,
    ) {
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', ComiteRevisao::class);

        $result = $this->service->list($request->only(['status', 'search', 'per_page']));
        $result->setCollection(
            $result->getCollection()->map(fn (ComiteRevisao $review) => (new ComiteRevisaoResource($review))->resolve())
        );

        return ApiResponseService::paginated($result, 'Revisões de comitê carregadas com sucesso');
    }

    public function store(Request $request)
    {
        Gate::authorize('create', ComiteRevisao::class);

        $validated = $request->validate([
            'terreno_id' => ['required', 'integer', 'exists:terrenos,id'],
            'viabilidade_id' => ['nullable', 'integer', 'exists:viabilidades,id'],
            'status' => ['nullable', 'string'],
            'required_departments' => ['nullable', 'array'],
            'required_departments.*' => ['string'],
        ]);

        $review = $this->service->create($validated, $request->user());

        return ApiResponseService::created(new ComiteRevisaoResource($review), 'Comitê criado com sucesso');
    }

    public function show(string $id)
    {
        $review = ComiteRevisao::findOrFail($id);
        Gate::authorize('view', $review);

        return ApiResponseService::success(new ComiteRevisaoResource($this->service->show($review)));
    }

    public function upsertDepartmentReview(Request $request, string $id)
    {
        $review = ComiteRevisao::findOrFail($id);
        Gate::authorize('update', $review);

        $validated = $request->validate([
            'department_code' => ['required', 'string'],
            'reviewer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'decision' => ['required', 'string', 'in:aprovado,aprovado_com_ressalvas,reprovado'],
            'comments' => ['nullable', 'string'],
            'checklist_completed' => ['nullable', 'boolean'],
        ]);

        $updated = $this->service->upsertDepartmentReview(
            $review,
            $validated,
            $request->user(),
            $this->workflowService,
        );

        return ApiResponseService::success(new ComiteRevisaoResource($updated), 'Parecer registrado com sucesso');
    }

    public function finalize(Request $request, string $id)
    {
        $review = ComiteRevisao::findOrFail($id);
        Gate::authorize('update', $review);

        $validated = $request->validate([
            'final_decision' => ['required', 'string', 'in:aprovado_comite,aprovado_com_ressalvas,reprovado_comite'],
            'final_comments' => ['nullable', 'string'],
            'pendencias' => ['nullable', 'array'],
            'pendencias.*.title' => ['required_with:pendencias', 'string'],
            'pendencias.*.description' => ['nullable', 'string'],
            'pendencias.*.severity' => ['nullable', 'string'],
            'pendencias.*.department_code' => ['nullable', 'string'],
            'pendencias.*.responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'pendencias.*.due_date' => ['nullable', 'date'],
        ]);

        $updated = $this->service->finalize($review, $validated, $request->user(), $this->workflowService);

        return ApiResponseService::success(new ComiteRevisaoResource($updated), 'Decisão de comitê registrada com sucesso');
    }
}
