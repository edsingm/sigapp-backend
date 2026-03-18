<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegionalResource extends JsonResource
{
    /**
     * Transformar o recurso em um array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'estado' => $this->estado,
            'cidade' => $this->cidade,
            'endereco' => $this->endereco,
            'numero' => $this->numero,
            'telefone' => $this->telefone,
            'celular' => $this->celular,
            'observacoes' => $this->observacoes,
            'responsavel' => new UserResource($this->whenLoaded('responsavel')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
        ];
    }
}
