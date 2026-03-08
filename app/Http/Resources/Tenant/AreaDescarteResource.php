<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AreaDescarteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terreno_id' => $this->terreno_id,
            'motivo_descarte' => $this->motivo_descarte,
            'descricao_descarte' => $this->descricao_descarte,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relacionamentos
            'terreno' => new TerrenoResource($this->whenLoaded('terreno')),
            'created_by_user' => new UserResource($this->whenLoaded('createdBy')),
        ];
    }
}
