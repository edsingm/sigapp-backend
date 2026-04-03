<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VeiculoResource extends JsonResource
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
            'placa' => $this->placa,
            'modelo' => $this->modelo,
            'marca' => $this->marca,
            'ano' => $this->ano,
            'cor' => $this->cor,
            'chassi' => $this->chassi,
            'renavam' => $this->renavam,
            'quilometragem' => $this->quilometragem,
            'tipo_combustivel' => $this->tipo_combustivel,
            'tipo_combustivel_label' => $this->getTipoCombustivelLabel(),
            'capacidade_passageiros' => $this->capacidade_passageiros,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'observacoes' => $this->observacoes,
            'foto' => $this->foto,
            'criado_por' => $this->whenLoaded('criadoPor', function () {
                return [
                    'id' => $this->criadoPor->id,
                    'name' => $this->criadoPor->name,
                ];
            }),
            'atualizado_por' => $this->whenLoaded('atualizadoPor', function () {
                return [
                    'id' => $this->atualizadoPor->id,
                    'name' => $this->atualizadoPor->name,
                ];
            }),
            'requisicoes_count' => $this->whenCounted('requisicoes'),
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
            'disponivel' => 'Disponível',
            'em_uso' => 'Em Uso',
            'manutencao' => 'Em Manutenção',
            'inativo' => 'Inativo',
            default => $this->status,
        };
    }

    /**
     * Retorna o label do tipo de combustível
     */
    private function getTipoCombustivelLabel(): string
    {
        return match ($this->tipo_combustivel) {
            'gasolina' => 'Gasolina',
            'etanol' => 'Etanol',
            'flex' => 'Flex',
            'diesel' => 'Diesel',
            'eletrico' => 'Elétrico',
            'hibrido' => 'Híbrido',
            default => $this->tipo_combustivel,
        };
    }
}
