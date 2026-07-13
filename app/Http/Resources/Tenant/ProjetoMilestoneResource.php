<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ProjetoMilestone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjetoMilestoneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ProjetoMilestone $milestone */
        $milestone = $this->resource;

        return [
            'id' => $milestone->id,
            'projeto_id' => $milestone->projeto_id,
            'name' => $milestone->name,
            'description' => $milestone->description,
            'status' => $milestone->status,
            'planned_start' => $milestone->planned_start?->toDateString(),
            'planned_end' => $milestone->planned_end?->toDateString(),
            'predicted_start' => $milestone->predicted_start?->toDateString(),
            'predicted_end' => $milestone->predicted_end?->toDateString(),
            'actual_start' => $milestone->actual_start?->toDateString(),
            'actual_end' => $milestone->actual_end?->toDateString(),
            'responsible_id' => $milestone->responsible_id,
            'responsible' => $this->whenLoaded('responsavel', fn () => [
                'id' => $milestone->responsavel?->id,
                'name' => $milestone->responsavel?->name,
                'email' => $milestone->responsavel?->email,
            ]),
            'weight' => $milestone->weight,
            'position' => $milestone->position,
            'is_critical' => $milestone->is_critical,
            'created_at' => $milestone->created_at?->toIso8601String(),
            'updated_at' => $milestone->updated_at?->toIso8601String(),
        ];
    }
}
