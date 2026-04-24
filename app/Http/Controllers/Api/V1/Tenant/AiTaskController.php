<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreAiTaskRequest;
use App\Http\Requests\Tenant\UpdateAiTaskRequest;
use App\Repositories\Tenant\TaskRepository;
use App\Repositories\Tenant\TerrenoRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AiTaskController extends Controller
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly TerrenoRepository $terrenoRepository,
    ) {}

    /**
     * Cria tarefa via API (para uso pela IA).
     */
    public function store(StoreAiTaskRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $terreno = $this->terrenoRepository->findOrFail($validated['terreno_id']);
        if (Gate::denies('update', $terreno)) {
            return new JsonResponse(['message' => 'Acesso negado ao terreno.'], 403);
        }

        $task = $this->taskRepository->create([
            'terreno_id' => $validated['terreno_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'status' => $validated['status'] ?? 'open',
            'priority' => $validated['priority'] ?? 'normal',
            'due_date' => $validated['due_date'] ?? null,
            'related_type' => $validated['related_type'] ?? null,
            'related_id' => $validated['related_id'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return new JsonResponse([
            'data' => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toDateString(),
            ],
        ], 201);
    }

     /**
     * Atualiza tarefa via API (para uso pela IA).
     */
    public function update(int $taskId, UpdateAiTaskRequest $request): JsonResponse
    {
        $task = $this->taskRepository->find($taskId);
        if (! $task) {
            return new JsonResponse(['message' => 'Tarefa não encontrada.'], 404);
        }

        $terreno = $this->terrenoRepository->findOrFail($task->terreno_id);
        if (! $terreno || Gate::denies('update', $terreno)) {
            return new JsonResponse(['message' => 'Acesso negado ao terreno.'], 403);
        }

        $validated = $request->validated();
        $changes = [];
        $updates = ['updated_by' => auth()->id()];

        if (isset($validated['status'])) {
            $changes['status'] = [$task->status, $validated['status']];
            $updates['status'] = $validated['status'];
            if (in_array($validated['status'], ['concluded', 'cancelled'], true)) {
                $updates['completed_at'] = now();
            }
        }

        if (isset($validated['assigned_to']) && $task->assigned_to !== $validated['assigned_to']) {
            $changes['assigned_to'] = [$task->assigned_to, $validated['assigned_to']];
            $updates['assigned_to'] = $validated['assigned_to'];
        }

        if (empty($changes)) {
            return new JsonResponse(['message' => 'Nenhuma alteração informada.'], 400);
        }

        $task = $this->taskRepository->update($task, $updates);

        return new JsonResponse([
            'data' => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'assigned_to' => $task->assigned_to,
                'changes' => $changes,
            ],
        ]);
    }
}
