<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreAiTaskRequest;
use App\Http\Requests\Tenant\UpdateAiTaskRequest;
use App\Services\ApiResponseService;
use App\Services\Tenant\AiTaskService;
use App\Services\Tenant\TerrenoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AiTaskController extends Controller
{
    public function __construct(
        private readonly AiTaskService $taskService,
        private readonly TerrenoService $terrenoService,
    ) {}

    /**
     * Cria tarefa via API (para uso pela IA).
     */
    public function store(StoreAiTaskRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $terreno = $this->terrenoService->findOrFail($validated['terreno_id']);

        if (Gate::denies('update', $terreno)) {
            return ApiResponseService::forbidden('Acesso negado ao terreno.');
        }

        $task = $this->taskService->create($validated, (int) auth()->id());

        return ApiResponseService::created([
            'id' => $task->getKey(),
            'title' => $task->getAttribute('title'),
            'status' => $task->getAttribute('status'),
            'priority' => $task->getAttribute('priority'),
            'due_date' => $task->getAttribute('due_date')?->toDateString(),
        ]);
    }

    /**
     * Atualiza tarefa via API (para uso pela IA).
     */
    public function update(int $taskId, UpdateAiTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->findTask($taskId);
        if (! $task) {
            return ApiResponseService::notFound('Tarefa não encontrada.');
        }

        $terreno = $this->terrenoService->findOrFail((int) $task->getAttribute('terreno_id'));
        if (Gate::denies('update', $terreno)) {
            return ApiResponseService::forbidden('Acesso negado ao terreno.');
        }

        $result = $this->taskService->applyUpdate($task, $request->validated(), (int) auth()->id());

        if ($result === null) {
            return ApiResponseService::error('NO_CHANGES', 'Nenhuma alteração informada.', null, 400);
        }

        $task = $result['task'];

        return ApiResponseService::success([
            'id' => $task->getKey(),
            'title' => $task->getAttribute('title'),
            'status' => $task->getAttribute('status'),
            'assigned_to' => $task->getAttribute('assigned_to'),
            'changes' => $result['changes'],
        ]);
    }
}
