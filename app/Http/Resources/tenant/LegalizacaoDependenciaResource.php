<?php

namespace App\Http\Resources\tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegalizacaoDependenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legalizacao_id' => $this->legalizacao_id,
            'etapa_origem_id' => $this->etapa_origem_id,
            'etapa_destino_id' => $this->etapa_destino_id,
            'tipo' => $this->tipo,
            'etapa_origem' => $this->whenLoaded('etapaOrigem', fn() => [
                'id' => $this->etapaOrigem->id,
                'titulo' => $this->etapaOrigem->titulo,
            ]),
            'etapa_destino' => $this->whenLoaded('etapaDestino', fn() => [
                'id' => $this->etapaDestino->id,
                'titulo' => $this->etapaDestino->titulo,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
