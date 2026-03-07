<?php

namespace App\Http\Resources\tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\tenant\ProdutoResource;
use App\Http\Resources\tenant\TerrenoResource;

class TerrenoProdutoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terreno_id' => $this->terreno_id,
            'produto_id' => $this->produto_id,
            'unidades' => $this->unidades,
            'valor' => $this->valor,
            'permuta' => $this->permuta,
            'pgto_por_lote' => $this->pgto_por_lote,
            'observacoes' => $this->observacoes,
            'produto' => new ProdutoResource($this->whenLoaded('produto')),
            'terreno' => new TerrenoResource($this->whenLoaded('terreno')),
        ];
    }
}