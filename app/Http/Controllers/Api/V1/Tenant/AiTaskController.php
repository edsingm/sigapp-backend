<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AiTaskController extends Controller
{
    /**
     * Cria tarefa via API (para uso pela IA).
     */
    public function store(Request $request): JsonResponse
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return new JsonResponse(['message' => 'Acesso negado.'], 403);
        }

        $validated = $request->validate([
            'terreno_id' => 'required|integer|exists:terrenos,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|integer',
            'status' => 'nullable|in:open,in_progress,concluded,cancelled',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'due_date' => 'nullable|date',
            'related_type' => 'nullable|string',
            'related_id' => 'nullable|integer',
        ]);

        $terreno = Terreno::find($validated['terreno_id']);
        if (Gate::denies('update', $terreno)) {
            return new JsonResponse(['message' => 'Acesso negado ao terreno.'], 403);
        }

        $task = Task::create([
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
    public function update(int $taskId, Request $request): JsonResponse
    {
        $task = Task::find($taskId);
        if (! $task) {
            return new JsonResponse(['message' => 'Tarefa não encontrada.'], 404);
        }

        $terreno = Terreno::find($task->terreno_id);
        if (! $terreno || Gate::denies('update', $terreno)) {
            return new JsonResponse(['message' => 'Acesso negado ao terreno.'], 403);
        }

        $validated = $request->validate([
            'status' => 'nullable|in:open,in_progress,concluded,cancelled',
            'assigned_to' => 'nullable|integer',
        ]);

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

        $task->update($updates);

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
