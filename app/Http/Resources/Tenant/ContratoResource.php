<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContratoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terreno_id' => $this->terreno_id,
            'negociacao_id' => $this->negociacao_id,
            'contract_type' => $this->contract_type,
            'contract_number' => $this->contract_number,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,
            'file_path' => $this->file_path,
            'notes' => $this->notes,
            'partes' => ContratoParteResource::collection($this->whenLoaded('partes')),
        ];
    }
}
