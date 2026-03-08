<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegalizacaoEtapaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $custos = is_array($this->custos) ? $this->custos : [];

        if (empty($custos) && ($this->tipo_custo !== null || $this->valor_custo !== null)) {
            $custos[] = [
                'tipo_custo' => $this->tipo_custo,
                'valor_custo' => $this->valor_custo,
                'custo_pago' => $this->custo_pago,
            ];
        }

        $custos = array_values(array_map(function ($custo) {
            $valor = $custo['valor_custo'] ?? $custo['valor'] ?? null;
            $pago = $custo['custo_pago'] ?? $custo['pago'] ?? $custo['foi_pago'] ?? false;

            return [
                'tipo_custo' => $custo['tipo_custo'] ?? $custo['tipo'] ?? null,
                'valor_custo' => $valor !== null ? (float) $valor : null,
                'custo_pago' => (bool) $pago,
                // Compatibilidade retroativa no item.
                'tipo' => $custo['tipo_custo'] ?? $custo['tipo'] ?? null,
                'valor' => $valor !== null ? (float) $valor : null,
                'pago' => (bool) $pago,
                'foi_pago' => (bool) $pago,
            ];
        }, $custos));

        $temCustos = !empty($custos);
        $valorCustoTotal = $temCustos
            ? (float) array_sum(array_map(fn ($custo) => (float) ($custo['valor_custo'] ?? 0), $custos))
            : ($this->valor_custo !== null ? (float) $this->valor_custo : null);
        $tipoCustoResumo = $temCustos
            ? (count($custos) === 1 ? ($custos[0]['tipo_custo'] ?? null) : ($this->tipo_custo ?: 'Diversos'))
            : $this->tipo_custo;
        $custoPagoResumo = $temCustos
            ? collect($custos)->every(fn ($custo) => (bool) ($custo['custo_pago'] ?? false))
            : (bool) $this->custo_pago;

        return [
            'id' => $this->id,
            'legalizacao_id' => $this->legalizacao_id,
            'parent_id' => $this->parent_id,
            'titulo' => $this->titulo,
            'descricao' => $this->descricao,
            'ordem' => $this->ordem,
            'status' => $this->status,
            'inicio_planejado' => $this->inicio_planejado?->format('Y-m-d'),
            'fim_planejado' => $this->fim_planejado?->format('Y-m-d'),
            'inicio_real' => $this->inicio_real?->format('Y-m-d'),
            'fim_real' => $this->fim_real?->format('Y-m-d'),
            'percentual' => $this->percentual,
            'responsavel_id' => $this->responsavel_id,
            'responsavel' => $this->whenLoaded('responsavel', fn() => [
                'id' => $this->responsavel->id,
                'name' => $this->responsavel->name,
            ]),
            'cor' => $this->cor,
            'custos' => $custos,
            'tipo_custo' => $tipoCustoResumo,
            'valor_custo' => $valorCustoTotal,
            // Compatibilidade retroativa com o frontend legado.
            'custo_previsto' => $valorCustoTotal,
            'custo_pago' => (bool) $custoPagoResumo,
            'foi_pago' => (bool) $custoPagoResumo,
            'created_by' => $this->whenLoaded('createdBy', fn() => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'updated_by' => $this->whenLoaded('updatedBy', fn() => [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
            ]),
            'dependencias' => $this->whenLoaded('dependenciasDestino', function () {
                return $this->dependenciasDestino->map(fn($dep) => [
                    'id' => $dep->id,
                    'origem_id' => $dep->etapa_origem_id,
                    'destino_id' => $dep->etapa_destino_id,
                    'tipo' => $dep->tipo,
                ]);
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
