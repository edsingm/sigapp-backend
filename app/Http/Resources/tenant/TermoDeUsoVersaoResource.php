<?php

namespace App\Http\Resources\tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TermoDeUsoVersaoResource extends JsonResource
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
            'versao' => $this->versao,
            'conteudo' => $this->conteudo,
            'criado_em' => $this->created_at,
            'criado_por' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy?->id,
                    'name' => $this->createdBy?->name,
                    'email' => $this->createdBy?->email,
                ];
            }),
        ];
    }
}
