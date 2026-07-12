<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Comment;
use App\Models\Tenant\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskRepository
{
    public function find(int|string $id): ?Task
    {
        return Task::query()->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Task
    {
        return Task::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data): Task
    {
        $task->update($data);

        return $task->refresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Task::query()
            ->with(['assignedUser', 'createdBy'])
            ->latest('created_at')
            ->latest('id');

        if (isset($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $query->whereIn('priority', $filters['priority']);
        }

        if (isset($filters['assignee_id'])) {
            $query->where('assigned_to', $filters['assignee_id']);
        }

        if (isset($filters['related_entity_type'])) {
            $query->where('related_type', $filters['related_entity_type']);
        }

        if (isset($filters['related_entity_id'])) {
            $query->where('related_id', $filters['related_entity_id']);
        }

        if (isset($filters['due_before'])) {
            $query->whereDate('due_date', '<=', $filters['due_before']);
        }

        if (isset($filters['due_after'])) {
            $query->whereDate('due_date', '>=', $filters['due_after']);
        }

        if (($filters['overdue'] ?? false) === true) {
            $query->whereNull('completed_at')
                ->whereDate('due_date', '<', today());
        }

        return $query->paginate(
            (int) ($filters['per_page'] ?? 30),
            ['*'],
            'page',
            (int) ($filters['page'] ?? 1),
        );
    }

    /**
     * @return Collection<int, Comment>
     */
    public function comments(Task $task): Collection
    {
        return Comment::query()
            ->with('user')
            ->where('related_type', 'task')
            ->where('related_id', $task->getKey())
            ->latest('created_at')
            ->get();
    }

    public function createComment(Task $task, int $userId, string $body): Comment
    {
        return Comment::query()->create([
            'terreno_id' => $task->terreno_id,
            'related_type' => 'task',
            'related_id' => $task->getKey(),
            'user_id' => $userId,
            'comment' => $body,
        ]);
    }

    /**
     * @return Collection<int, Task>
     */
    public function dependencies(Task $task): Collection
    {
        return $task->dependencies()->with(['assignedUser'])->get();
    }

    public function hasDependency(Task $task, int $dependencyId): bool
    {
        return $task->dependencies()->whereKey($dependencyId)->exists();
    }

    public function addDependency(Task $task, int $dependencyId): void
    {
        $task->dependencies()->syncWithoutDetaching([$dependencyId]);
    }

    public function removeDependency(Task $task, int $dependencyId): void
    {
        $task->dependencies()->detach($dependencyId);
    }
}
