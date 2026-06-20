<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Task;
use App\Repositories\Tenant\TaskRepository;

class AiTaskService
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
    ) {}

    public function findTask(int $id): ?Task
    {
        return $this->taskRepository->find($id);
    }

    /**
     * Cria uma tarefa a partir dos dados validados.
     *
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, int $actorId): Task
    {
        return $this->taskRepository->create([
            'terreno_id' => $validated['terreno_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'status' => $validated['status'] ?? 'open',
            'priority' => $validated['priority'] ?? 'normal',
            'due_date' => $validated['due_date'] ?? null,
            'related_type' => $validated['related_type'] ?? null,
            'related_id' => $validated['related_id'] ?? null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    /**
     * Aplica atualizações de status/atribuição a uma tarefa, registrando as mudanças.
     *
     * Retorna o conjunto de mudanças aplicadas, ou null quando nada foi alterado.
     *
     * @param  array<string, mixed>  $validated
     * @return array{task: Task, changes: array<string, array{0: mixed, 1: mixed}>}|null
     */
    public function applyUpdate(Task $task, array $validated, int $actorId): ?array
    {
        $changes = [];
        $updates = ['updated_by' => $actorId];

        if (isset($validated['status'])) {
            $changes['status'] = [(string) $task->getAttribute('status'), $validated['status']];
            $updates['status'] = $validated['status'];
            if (in_array($validated['status'], ['concluded', 'cancelled'], true)) {
                $updates['completed_at'] = now();
            }
        }

        if (isset($validated['assigned_to']) && $task->getAttribute('assigned_to') !== $validated['assigned_to']) {
            $changes['assigned_to'] = [$task->getAttribute('assigned_to'), $validated['assigned_to']];
            $updates['assigned_to'] = $validated['assigned_to'];
        }

        if (empty($changes)) {
            return null;
        }

        $task = $this->taskRepository->update($task, $updates);

        return ['task' => $task, 'changes' => $changes];
    }
}
