<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegalizacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terreno_id' => $this->terreno_id,
            'terreno' => $this->whenLoaded('terreno', fn() => [
                'id' => $this->terreno->id,
                'nome' => $this->terreno->nome,
                'codigo_imovel' => $this->terreno->codigo_imovel,
                'endereco' => $this->terreno->endereco,
                'cidade' => $this->terreno->cidade?->nome,
                'estado' => $this->terreno->cidade?->estado,
                'status' => $this->terreno->terrenoStatus?->nome,
            ]),
            'responsavel_id' => $this->responsavel_id,
            'responsavel' => $this->whenLoaded('responsavel', fn() => [
                'id' => $this->responsavel->id,
                'name' => $this->responsavel->name,
                'email' => $this->responsavel->email,
            ]),
            'nome' => $this->nome,
            'status' => $this->status,
            'data_inicio_planejada' => $this->data_inicio_planejada?->format('Y-m-d'),
            'data_fim_planejada' => $this->data_fim_planejada?->format('Y-m-d'),
            'data_inicio_prevista' => $this->data_inicio_prevista?->format('Y-m-d'),
            'data_conclusao_prevista' => $this->data_conclusao_prevista?->format('Y-m-d'),
            'data_inicio_real' => $this->data_inicio_real?->format('Y-m-d'),
            'data_fim_real' => $this->data_fim_real?->format('Y-m-d'),
            'percentual_concluido' => $this->percentual_concluido,
            'progresso' => $this->percentual_concluido ?? 0,
            'custo_total_previsto' => $this->custo_total_previsto ? (float) $this->custo_total_previsto : null,
            'observacoes' => $this->observacoes,
            'created_by' => $this->whenLoaded('createdBy', fn() => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'updated_by' => $this->whenLoaded('updatedBy', fn() => [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
            ]),
            'etapas_count' => $this->whenCounted('etapas'),
            'total_etapas' => $this->whenCounted('etapas'),
            'etapas' => LegalizacaoEtapaResource::collection($this->whenLoaded('etapas')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
