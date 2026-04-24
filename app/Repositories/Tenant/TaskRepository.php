<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Task;

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
}
