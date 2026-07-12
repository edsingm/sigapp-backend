<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityReferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Task $task */
        $task = $this->resource;

        return [
            'id' => $task->id,
            'type' => 'task',
            'label' => $task->title,
            'href' => null,
        ];
    }
}
