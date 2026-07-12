<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\EntityActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var EntityActivity $activity */
        $activity = $this->resource;

        return [
            'id' => $activity->id,
            'entity_type' => $activity->entity_type,
            'entity_id' => $activity->entity_id,
            'action' => $activity->action,
            'summary' => $activity->summary,
            'payload' => $activity->payload_json,
            'type' => $activity->action,
            'title' => $activity->summary,
            'description' => $activity->summary,
            'actor' => $this->whenLoaded('user', fn () => [
                'id' => $activity->user?->id,
                'name' => $activity->user?->name,
                'email' => $activity->user?->email,
                'avatar_url' => null,
            ]),
            'entity' => [
                'id' => $activity->entity_id,
                'type' => $activity->entity_type,
                'label' => $this->whenLoaded('terreno', fn () => $activity->terreno?->nome),
                'href' => null,
            ],
            'metadata' => $activity->payload_json ?? [],
            'happened_at' => $activity->happened_at?->toIso8601String(),
            'occurred_at' => $activity->happened_at?->toIso8601String(),
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }
}
