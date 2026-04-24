<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ViabilidadeCalculationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{viabilidade?: mixed, dre_resultados?: mixed} $payload */
        $payload = is_array($this->resource) ? $this->resource : [];
        $viabilidade = $payload['viabilidade'] ?? null;

        return [
            'viabilidade' => $viabilidade !== null ? new ViabilidadeResource($viabilidade) : null,
            'dre_resultados' => $payload['dre_resultados'] ?? [],
        ];
    }
}
