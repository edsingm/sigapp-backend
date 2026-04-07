<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Terreno;
use App\Services\Tenant\LandWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class AiWorkflowController extends Controller
{
    public function __construct(
        protected LandWorkflowService $workflowService
    ) {}

    public function transition(Request $request): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $validated = $request->validate([
            'terreno_id' => 'required|integer|exists:terrenos,id',
            'target_status' => 'required|string',
            'reason_code' => 'nullable|string',
            'reason_notes' => 'nullable|string',
        ]);

        $terreno = Terreno::find($validated['terreno_id']);
        if (Gate::denies('update', $terreno)) {
            return new JsonResponse(['message' => 'Acesso negado ao terreno.'], 403);
        }

        try {
            $updated = $this->workflowService->transition(
                $terreno,
                $validated['target_status'],
                auth()->user(),
                $validated['reason_code'] ?? 'ai_suggested',
                $validated['reason_notes'] ?? 'Transição sugerida pelo SIG IA.',
            );

            return new JsonResponse([
                'data' => [
                    'message' => "Workflow do terreno '{$updated->nome}' avançado com sucesso.",
                    'workflow' => [
                        'stage' => $updated->workflow_stage,
                        'status_code' => $updated->workflow_status_code,
                        'changed_at' => $updated->workflow_status_changed_at?->toIso8601String(),
                    ],
                ],
            ]);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'message' => "Não foi possível realizar a transição: {$e->getMessage()}",
            ], 422);
        }
    }
}
