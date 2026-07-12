<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Legalizacao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegalizacaoControlCenterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Legalizacao $legalizacao */
        $legalizacao = $this->resource;

        $criticalOpen = $legalizacao->pendencias->where('is_critical', true)->where('status', 'open')->count();
        $overdueStages = $legalizacao->etapas->filter(fn ($stage): bool => $stage->fim_planejado?->isPast() && $stage->percentual < 100)->count();

        return [
            'id' => $legalizacao->id,
            'terreno_id' => $legalizacao->terreno_id,
            'nome' => $legalizacao->nome,
            'status' => $legalizacao->status,
            'percentual_concluido' => $legalizacao->percentual_concluido,
            'data_inicio_planejada' => $legalizacao->data_inicio_planejada?->toDateString(),
            'data_conclusao_prevista' => $legalizacao->data_conclusao_prevista?->toDateString(),
            'critical_open_pendencies' => $criticalOpen,
            'overdue_stages' => $overdueStages,
            'stages_count' => $legalizacao->etapas->count(),
            'terreno' => $this->whenLoaded('terreno', fn () => [
                'id' => $legalizacao->terreno?->id,
                'nome' => $legalizacao->terreno?->nome,
            ]),
        ];
    }
}
