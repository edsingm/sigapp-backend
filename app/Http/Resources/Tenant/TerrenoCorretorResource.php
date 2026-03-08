<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerrenoCorretorResource extends JsonResource
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
            'valor_proposta' => $this->valor_proposta,
            'valor_negociado' => $this->valor_negociado,
            'comissao_percentual' => $this->comissao_percentual,
            'comissao_valor' => $this->comissao_valor,
            'forma_pagamento' => $this->forma_pagamento,
            'prazo_negociacao' => $this->prazo_negociacao,
            'data_primeira_visita' => $this->data_primeira_visita?->format('Y-m-d'),
            'data_ultima_negociacao' => $this->data_ultima_negociacao?->format('Y-m-d'),
            'status_negociacao' => $this->status_negociacao,
            'observacoes_corretor' => $this->observacoes_corretor,
            'documentos_apresentados' => $this->documentos_apresentados,
            'restricoes_area' => $this->restricoes_area,
            'potencial_construtivo' => $this->potencial_construtivo,
            'infraestrutura_local' => $this->infraestrutura_local,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
