<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\TerrenoResource;
use App\Models\Tenant\Terreno;
use App\Services\ApiResponseService;
use App\Services\Tenant\LandWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TerrenoWorkflowController extends Controller
{
    public function __construct(
        protected LandWorkflowService $workflowService
    ) {}

    /**
     * Exibir o status atual e as opções de workflow para um terreno.
     */
    public function show(string $id)
    {
        $terreno = Terreno::findOrFail($id);
        Gate::authorize('view', $terreno);
        $transitionOptions = $this->workflowService->transitionOptions(
            $terreno->load([
                'proprietarios',
                'contatos',
                'viabilidadeAtual',
                'comiteAtual',
                'contratoAtual.partes',
                'legalizacao.etapas',
                'legalizacao.pendencias',
            ])
        );

        return ApiResponseService::success([
            'current_status' => $terreno->workflow_status_code,
            'current_stage' => $terreno->workflow_stage,
            'available_transitions' => $transitionOptions['available'],
            'blocked_transitions' => $transitionOptions['blocked'],
            'checklist' => $this->workflowService->checklist($terreno->load(['proprietarios', 'contatos', 'comiteAtual', 'viabilidadeAtual', 'contratoAtual', 'legalizacao'])),
        ]);
    }

    /**
     * Atualizar o status do workflow de um terreno (transição de status).
     */
    public function update(Request $request, string $id)
    {
        $terreno = Terreno::findOrFail($id);
        Gate::authorize('update', $terreno);

        $validated = $request->validate([
            'target_status' => ['required', 'string'],
            'reason_code' => ['nullable', 'string', 'max:255'],
            'reason_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $updated = $this->workflowService->transition(
            $terreno,
            $validated['target_status'],
            $request->user(),
            $validated['reason_code'] ?? null,
            $validated['reason_notes'] ?? null,
        );

        return ApiResponseService::success(
            new TerrenoResource($updated),
            'Workflow do terreno atualizado com sucesso.'
        );
    }

    /**
     * Atualizar os dados de qualificação de um terreno no workflow.
     */
    public function updateQualification(Request $request, string $id)
    {
        $terreno = Terreno::findOrFail($id);
        Gate::authorize('update', $terreno);

        $validated = $request->validate([
            'urbanistic_preliminary' => ['nullable', 'array'],
            'commercial' => ['nullable', 'array'],
            'desired_product' => ['nullable', 'array'],
            'preliminary_risks' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array'],
            'mark_as_completed' => ['nullable', 'boolean'],
        ]);

        $updated = $this->workflowService->updateQualification($terreno, $validated, $request->user());

        return ApiResponseService::success(
            new TerrenoResource($updated->load(['proprietarios', 'contatos', 'viabilidadeAtual', 'comiteAtual', 'negociacaoAtual', 'contratoAtual', 'legalizacao', 'tasks', 'statusHistories', 'activities'])),
            'Qualificação atualizada com sucesso.'
        );
    }
}
