<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ListTasksRequest;
use App\Http\Requests\Tenant\StoreTaskCommentRequest;
use App\Http\Requests\Tenant\StoreTaskRequest;
use App\Http\Requests\Tenant\UpdateTaskRequest;
use App\Http\Resources\Tenant\CommentResource;
use App\Http\Resources\Tenant\TaskResource;
use App\Models\Tenant\Task;
use App\Repositories\Tenant\TaskRepository;
use App\Services\ApiResponseService;
use App\Services\Tenant\TaskService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly TaskRepository $taskRepository,
    ) {}

    public function index(ListTasksRequest $request): JsonResponse
    {
        try {
            $tasks = $this->taskService->paginate($request->validated());

            return TaskResource::collection($tasks)->response();
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('INVALID_TASK_FILTER', $exception->getMessage(), null, 422);
        }
    }

    public function myQueue(ListTasksRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $filters['assignee_id'] = $request->user()->getAuthIdentifier();

        return TaskResource::collection($this->taskService->paginate($filters))->response();
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        try {
            $task = $this->taskService->create(
                $request->validated(),
                (int) $request->user()->getAuthIdentifier(),
            );

            return ApiResponseService::created(new TaskResource($task->load(['assignedUser', 'createdBy'])));
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('INVALID_RELATED_ENTITY', $exception->getMessage(), null, 422);
        }
    }

    public function show(Task $task): JsonResponse
    {
        return ApiResponseService::success(
            new TaskResource($task->load(['assignedUser', 'createdBy', 'comments.user', 'dependencies.assignedUser'])),
        );
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        try {
            $updated = $this->taskService->update(
                $task,
                $request->validated(),
                (int) $request->user()->getAuthIdentifier(),
            );

            return ApiResponseService::success(new TaskResource($updated->load(['assignedUser', 'createdBy'])));
        } catch (InvalidArgumentException $exception) {
            return ApiResponseService::error('INVALID_RELATED_ENTITY', $exception->getMessage(), null, 422);
        }
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return ApiResponseService::noContent();
    }

    public function comments(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        $comment = $this->taskService->comment(
            $task,
            (int) $request->user()->getAuthIdentifier(),
            $request->validated('body'),
        );

        return ApiResponseService::created(new CommentResource($comment->load('user')));
    }

    public function listComments(ListTasksRequest $request, Task $task): JsonResponse
    {
        return CommentResource::collection($this->taskRepository->comments($task))->response();
    }
}
