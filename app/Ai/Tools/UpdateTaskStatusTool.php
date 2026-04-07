<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateTaskStatusTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Atualiza o status ou responsável de uma tarefa existente. Use para concluir tarefas ou reatribuir responsáveis.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para atualizar tarefas.';
        }

        $taskId = (int) ($request['task_id'] ?? 0);
        if ($taskId <= 0) {
            return 'Informe um task_id válido.';
        }

        $task = Task::find($taskId);
        if (! $task) {
            return "Tarefa {$taskId} não encontrada.";
        }

        $terreno = Terreno::find($task->terreno_id);
        if (! $terreno || Gate::denies('update', $terreno)) {
            return 'Acesso negado ao terreno vinculado a esta tarefa.';
        }

        $allowedStatuses = ['open', 'in_progress', 'concluded', 'cancelled'];
        $status = trim((string) ($request['status'] ?? ''));
        if ($status !== '' && ! in_array($status, $allowedStatuses, true)) {
            return 'Status inválido. Use: '.implode(', ', $allowedStatuses).'.';
        }

        $changes = [];
        $updates = ['updated_by' => auth()->id()];

        if ($status !== '') {
            $updates['status'] = $status;
            $changes['status'] = [$task->status, $status];
            if (in_array($status, ['concluded', 'cancelled'], true)) {
                $updates['completed_at'] = now();
            }
        }

        $assignedTo = (int) ($request['assigned_to'] ?? 0);
        if ($assignedTo > 0 && $task->assigned_to !== $assignedTo) {
            $updates['assigned_to'] = $assignedTo;
            $changes['assigned_to'] = [$task->assigned_to, $assignedTo];
        }

        if (empty($changes)) {
            return 'Nenhuma alteração informada. Informe status ou assigned_to.';
        }

        $task->update($updates);

        $payload = [
            'message' => "Tarefa '{$task->title}' atualizada com sucesso.",
            'changes' => $changes,
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'assigned_to' => $task->assigned_to,
                'due_date' => optional($task->due_date)?->toDateString(),
            ],
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ?: 'Falha ao serializar atualização.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->required(),
            'status' => $schema->string(),
            'assigned_to' => $schema->integer(),
        ];
    }
}
