<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Comment;
use App\Models\Tenant\Task;
use App\Repositories\Tenant\TaskRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class TaskService
{
    private const RELATED_TYPES = [
        'terreno',
        'viabilidade',
        'comite',
        'negociacao',
        'legalizacao',
        'projeto',
    ];

    public function __construct(
        private readonly TaskRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $this->validateRelatedType($filters['related_entity_type'] ?? null);

        return $this->repository->paginate($filters);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $actorId): Task
    {
        $this->validateRelatedType($data['related_type'] ?? null);

        return $this->repository->create([
            'terreno_id' => $data['terreno_id'] ?? null,
            'related_type' => $data['related_type'] ?? null,
            'related_id' => $data['related_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'status' => $data['status'] ?? 'open',
            'priority' => $data['priority'] ?? 'medium',
            'tags' => $data['tags'] ?? [],
            'due_date' => $data['due_at'] ?? null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data, int $actorId): Task
    {
        if (array_key_exists('related_type', $data)) {
            $this->validateRelatedType($data['related_type']);
        }

        $updates = [];
        foreach ([
            'title' => 'title',
            'description' => 'description',
            'assigned_to' => 'assigned_to',
            'status' => 'status',
            'priority' => 'priority',
            'tags' => 'tags',
            'due_at' => 'due_date',
            'related_type' => 'related_type',
            'related_id' => 'related_id',
        ] as $input => $column) {
            if (array_key_exists($input, $data)) {
                $updates[$column] = $data[$input];
            }
        }

        if (array_key_exists('status', $updates)) {
            if (in_array($updates['status'], ['concluida', 'concluded', 'completed', 'cancelled'], true)) {
                $updates['completed_at'] = now();
            } elseif (in_array($updates['status'], ['open', 'pendente', 'pending', 'in_progress', 'em_andamento', 'blocked'], true)) {
                $updates['completed_at'] = null;
            }
        }

        $updates['updated_by'] = $actorId;

        return $this->repository->update($task, $updates);
    }

    public function comment(Task $task, int $userId, string $body): Comment
    {
        return $this->repository->createComment($task, $userId, $body);
    }

    private function validateRelatedType(mixed $value): void
    {
        if ($value !== null && ! in_array($value, self::RELATED_TYPES, true)) {
            throw new InvalidArgumentException('Tipo de entidade relacionada inválido.');
        }
    }
}
