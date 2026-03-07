<?php

namespace App\Http\Resources\tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequisicaoVeiculoResource extends JsonResource
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
            'veiculo' => $this->whenLoaded('veiculo', function () {
                return [
                    'id' => $this->veiculo->id,
                    'placa' => $this->veiculo->placa,
                    'modelo' => $this->veiculo->modelo,
                    'marca' => $this->veiculo->marca,
                    'ano' => $this->veiculo->ano,
                ];
            }),
            'solicitante' => $this->whenLoaded('solicitante', function () {
                return [
                    'id' => $this->solicitante->id,
                    'name' => $this->solicitante->name,
                    'email' => $this->solicitante->email,
                ];
            }),
            'motorista' => $this->whenLoaded('motorista', function () {
                return $this->motorista ? [
                    'id' => $this->motorista->id,
                    'name' => $this->motorista->name,
                ] : null;
            }),
            'nome_motorista' => $this->nome_motorista,
            'nome_motorista_completo' => $this->getNomeMotoristaCompleto(),
            'cnh_motorista' => $this->cnh_motorista,
            'data_inicio' => $this->data_inicio?->format('Y-m-d H:i:s'),
            'data_fim' => $this->data_fim?->format('Y-m-d H:i:s'),
            'data_retirada' => $this->data_retirada?->format('Y-m-d H:i:s'),
            'data_devolucao' => $this->data_devolucao?->format('Y-m-d H:i:s'),
            'destino' => $this->destino,
            'motivo_uso' => $this->motivo_uso,
            'observacoes' => $this->observacoes,
            'km_inicial' => $this->km_inicial,
            'km_final' => $this->km_final,
            'km_percorrido' => $this->getQuilometragemPercorrida(),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'motivo_cancelamento' => $this->motivo_cancelamento,
            'aprovado_por' => $this->whenLoaded('aprovadoPor', function () {
                return $this->aprovadoPor ? [
                    'id' => $this->aprovadoPor->id,
                    'name' => $this->aprovadoPor->name,
                ] : null;
            }),
            'aprovado_em' => $this->aprovado_em?->format('Y-m-d H:i:s'),
            'pode_cancelar' => $this->podeCancelar(),
            'pode_aprovar' => $this->podeAprovar(),
            'pode_iniciar' => $this->podeIniciar(),
            'pode_finalizar' => $this->podeFinalizar(),
            'criado_por' => $this->whenLoaded('criadoPor', function () {
                return [
                    'id' => $this->criadoPor->id,
                    'name' => $this->criadoPor->name,
                ];
            }),
            'atualizado_por' => $this->whenLoaded('atualizadoPor', function () {
                return $this->atualizadoPor ? [
                    'id' => $this->atualizadoPor->id,
                    'name' => $this->atualizadoPor->name,
                ] : null;
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Retorna o label do status
     */
    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'pendente' => 'Pendente',
            'aprovada' => 'Aprovada',
            'em_uso' => 'Em Uso',
            'concluida' => 'Concluída',
            'cancelada' => 'Cancelada',
            'rejeitada' => 'Rejeitada',
            default => $this->status,
        };
    }
}
