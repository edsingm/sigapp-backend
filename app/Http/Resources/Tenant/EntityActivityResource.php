<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'action' => $this->action,
            'summary' => $this->summary,
            'payload' => $this->payload_json,
            'happened_at' => $this->happened_at?->toIso8601String(),
        ];
    }
}
