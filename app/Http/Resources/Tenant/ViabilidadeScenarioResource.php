<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ViabilidadeScenario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ViabilidadeScenarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ViabilidadeScenario $scenario */
        $scenario = $this->resource;
        $snapshot = $scenario->getAttribute('premises_snapshot');

        return [
            'id' => $scenario->getAttribute('id'),
            'viabilidade_id' => $scenario->getAttribute('viabilidade_id'),
            'name' => $scenario->getAttribute('name'),
            'scenario_type' => $scenario->getAttribute('scenario_type'),
            'status' => $scenario->getAttribute('status'),
            'premises' => is_array($snapshot) ? ($snapshot['overrides'] ?? []) : [],
            'results' => $scenario->getAttribute('results'),
            'formula_version' => $scenario->getAttribute('formula_version'),
            'input_hash' => $scenario->getAttribute('input_hash'),
            'created_by' => $scenario->getAttribute('created_by'),
            'calculated_at' => $scenario->getAttribute('calculated_at')?->toIso8601String(),
            'promoted_at' => $scenario->getAttribute('promoted_at')?->toIso8601String(),
            'error_message' => $scenario->getAttribute('status') === 'failed'
                ? $scenario->getAttribute('error_message')
                : null,
        ];
    }
}
