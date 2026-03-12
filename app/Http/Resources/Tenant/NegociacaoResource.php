<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NegociacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terreno_id' => $this->terreno_id,
            'status' => $this->status,
            'proposal_value' => $this->proposal_value !== null ? (float) $this->proposal_value : null,
            'business_model' => $this->business_model,
            'started_at' => $this->started_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'notes' => $this->notes,
            'eventos' => NegociacaoEventoResource::collection($this->whenLoaded('eventos')),
            'contratos' => ContratoResource::collection($this->whenLoaded('contratos')),
        ];
    }
}
