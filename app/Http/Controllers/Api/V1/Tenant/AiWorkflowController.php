<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\TransitionAiWorkflowRequest;
use App\Repositories\Tenant\TerrenoRepository;
use App\Services\ApiResponseService;
use App\Services\Tenant\LandWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class AiWorkflowController extends Controller
{
    public function __construct(
        protected LandWorkflowService $workflowService,
        private readonly TerrenoRepository $terrenoRepository,
    ) {}

    public function transition(TransitionAiWorkflowRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $terreno = $this->terrenoRepository->findOrFail($validated['terreno_id']);
        if (Gate::denies('update', $terreno)) {
            return ApiResponseService::forbidden('Acesso negado ao terreno.');
        }

        try {
            $updated = $this->workflowService->transition(
                $terreno,
                $validated['target_status'],
                auth()->user(),
                $validated['reason_code'] ?? 'ai_suggested',
                $validated['reason_notes'] ?? 'Transição sugerida pelo SIG IA.',
            );

            return ApiResponseService::success([
                'message' => "Workflow do terreno '{$updated->nome}' avançado com sucesso.",
                'workflow' => [
                    'stage' => $updated->workflow_stage,
                    'status_code' => $updated->workflow_status_code,
                    'changed_at' => $updated->workflow_status_changed_at?->toIso8601String(),
                ],
            ]);
        } catch (RuntimeException $e) {
            return ApiResponseService::error(
                'WORKFLOW_TRANSITION_FAILED',
                "Não foi possível realizar a transição: {$e->getMessage()}",
                null,
                422
            );
        }
    }
}
