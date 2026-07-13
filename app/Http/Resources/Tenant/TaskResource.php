<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Task $task */
        $task = $this->resource;

        return [
            'id' => $task->id,
            'terreno_id' => $task->terreno_id,
            'related_type' => $task->related_type,
            'related_id' => $task->related_id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'tags' => $task->tags ?? [],
            'due_date' => $task->due_date?->format('Y-m-d'),
            'due_at' => $task->due_date?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'assigned_to' => $task->assigned_to,
            'assignee' => $this->whenLoaded('assignedUser', fn () => [
                'id' => $task->assignedUser?->id,
                'name' => $task->assignedUser?->name,
                'email' => $task->assignedUser?->email,
                'avatar_url' => null,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $task->createdBy?->id,
                'name' => $task->createdBy?->name,
                'email' => $task->createdBy?->email,
                'avatar_url' => null,
            ]),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'dependencies' => EntityReferenceResource::collection($this->whenLoaded('dependencies')),
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => [
                'id' => $task->assignedUser?->id,
                'name' => $task->assignedUser?->name,
                'email' => $task->assignedUser?->email,
            ]),
        ];
    }
}
