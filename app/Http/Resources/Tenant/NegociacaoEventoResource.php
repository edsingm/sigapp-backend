<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NegociacaoEventoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'payload' => $this->payload_json,
            'notes' => $this->notes,
            'user_id' => $this->user_id,
            'happened_at' => $this->happened_at?->toIso8601String(),
        ];
    }
}
