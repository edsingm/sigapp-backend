<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerrenoComparisonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Terreno $terreno */
        $terreno = $this->resource;

        return [
            'id' => $terreno->id,
            'title' => $terreno->nome,
            'location' => [
                'address' => $terreno->endereco,
                'city_code' => $terreno->cidade_code,
                'state' => $terreno->estado,
            ],
            'area' => $terreno->area_calculada,
            'value' => $terreno->valor,
            'units' => null,
            'workflow_status' => $terreno->workflow_status_code,
            'products' => $this->whenLoaded('terrenoProdutos', fn () => $terreno->terrenoProdutos->map(fn ($item): array => [
                'id' => $item->produto_id,
                'units' => $item->unidades,
                'value' => $item->valor,
            ])->values()),
            'viability' => $this->whenLoaded('viabilidadeAtual', fn () => $terreno->viabilidadeAtual ? [
                'id' => $terreno->viabilidadeAtual->id,
                'approval_status' => $terreno->viabilidadeAtual->approval_status,
                'tir' => $terreno->viabilidadeAtual->getAttribute('tir'),
                'margin' => $terreno->viabilidadeAtual->getAttribute('margem'),
            ] : null),
            'data_quality' => [
                'level' => 'partial',
                'is_partial' => true,
                'missing_fields' => [],
            ],
        ];
    }
}
