<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateTaskTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Cria uma nova tarefa vinculada a um terreno. Use quando identificar pendências, inconsistências ou ações necessárias.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para criar tarefas.';
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

        $title = trim((string) ($request['title'] ?? ''));
        if ($title === '') {
            return 'O campo title é obrigatório.';
        }

        $description = trim((string) ($request['description'] ?? ''));
        $assignedTo = (int) ($request['assigned_to'] ?? 0);
        $status = trim((string) ($request['status'] ?? 'open'));
        $priority = trim((string) ($request['priority'] ?? 'normal'));
        $dueDate = trim((string) ($request['due_date'] ?? ''));
        $relatedType = trim((string) ($request['related_type'] ?? ''));
        $relatedId = (int) ($request['related_id'] ?? 0);

        $allowedStatuses = ['open', 'in_progress', 'concluded', 'cancelled'];
        if (! in_array($status, $allowedStatuses, true)) {
            return 'Status inválido. Use: '.implode(', ', $allowedStatuses).'.';
        }

        $allowedPriorities = ['low', 'normal', 'high', 'urgent'];
        if (! in_array($priority, $allowedPriorities, true)) {
            return 'Prioridade inválida. Use: '.implode(', ', $allowedPriorities).'.';
        }

        $task = Task::create([
            'terreno_id' => $terreno->id,
            'title' => $title,
            'description' => $description ?: null,
            'assigned_to' => $assignedTo > 0 ? $assignedTo : null,
            'status' => $status,
            'priority' => $priority,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'related_type' => $relatedType !== '' ? $relatedType : null,
            'related_id' => $relatedId > 0 ? $relatedId : null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $payload = [
            'message' => "Tarefa criada com sucesso para terreno {$terreno->nome} (ID: {$terreno->id}).",
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => optional($task->due_date)?->toDateString(),
                'assigned_to' => $task->assignedUser ? [
                    'id' => $task->assignedUser->id,
                    'name' => $task->assignedUser->name,
                ] : null,
            ],
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ?: 'Falha ao serializar tarefa criada.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()->required(),
            'title' => $schema->string()->required(),
            'description' => $schema->string(),
            'assigned_to' => $schema->integer(),
            'status' => $schema->string(),
            'priority' => $schema->string(),
            'due_date' => $schema->string()->format('date'),
            'related_type' => $schema->string(),
            'related_id' => $schema->integer(),
        ];
    }
}
