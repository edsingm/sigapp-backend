<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\Tenant\LandWorkflowService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class TransitionWorkflowTool implements Tool
{
    public function __construct(
        protected LandWorkflowService $workflowService
    ) {}

    public function description(): Stringable|string
    {
        return 'Avança o workflow de um terreno para o próximo estágio. Use quando detectar que todos os pré-requisitos foram cumpridos.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para gerenciar workflows.';
        }

        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        if ($terrenoId <= 0) {
            return 'Informe um terreno_id válido.';
        }

        $terreno = Terreno::find($terrenoId);
        if (! $terreno) {
            return "Terreno {$terrenoId} não encontrado.";
        }

        if (Gate::denies('update', $terreno)) {
            return "Acesso negado ao terreno {$terrenoId}.";
        }

        $targetStatus = trim((string) ($request['target_status'] ?? ''));
        if ($targetStatus === '') {
            return 'Informe o target_status desejado.';
        }

        $reasonNotes = trim((string) ($request['reason_notes'] ?? ''));
        $reasonCode = trim((string) ($request['reason_code'] ?? 'ai_suggested'));

        try {
            $updated = $this->workflowService->transition(
                $terreno,
                $targetStatus,
                auth()->user(),
                $reasonCode,
                $reasonNotes !== '' ? $reasonNotes : 'Transição sugerida pelo SIG IA.',
            );

            $payload = [
                'message' => "Workflow do terreno '{$updated->nome}' avançado com sucesso.",
                'workflow' => [
                    'previous_stage' => $updated->workflow_stage,
                    'new_stage' => $updated->workflow_stage,
                    'new_status_code' => $updated->workflow_status_code,
                    'changed_at' => optional($updated->workflow_status_changed_at)?->toAtomString(),
                ],
                'checklist' => $this->workflowService->checklist($updated),
            ];

            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ?: 'Falha ao serializar workflow.';
        } catch (RuntimeException $e) {
            return "Não foi possível realizar a transição: {$e->getMessage()}";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()->required(),
            'target_status' => $schema->string()->required(),
            'reason_code' => $schema->string(),
            'reason_notes' => $schema->string(),
        ];
    }
}
