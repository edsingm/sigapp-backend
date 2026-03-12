<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComiteRevisaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terreno_id' => $this->terreno_id,
            'viabilidade_id' => $this->viabilidade_id,
            'status' => $this->status,
            'final_decision' => $this->final_decision,
            'final_comments' => $this->final_comments,
            'required_departments' => $this->required_departments ?? [],
            'decided_by' => $this->decided_by,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'terreno' => new TerrenoResource($this->whenLoaded('terreno')),
            'viabilidade' => new ViabilidadeResource($this->whenLoaded('viabilidade')),
            'pareceres_departamento' => ComiteParecerDepartamentoResource::collection($this->whenLoaded('pareceresDepartamento')),
            'pendencias' => ComitePendenciaResource::collection($this->whenLoaded('pendencias')),
        ];
    }
}
