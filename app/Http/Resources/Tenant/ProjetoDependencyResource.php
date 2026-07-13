<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ProjetoDependency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjetoDependencyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ProjetoDependency $dependency */
        $dependency = $this->resource;

        return [
            'id' => $dependency->id,
            'projeto_id' => $dependency->projeto_id,
            'predecessor_milestone_id' => $dependency->predecessor_milestone_id,
            'successor_milestone_id' => $dependency->successor_milestone_id,
            'dependency_type' => $dependency->dependency_type,
            'lag_days' => $dependency->lag_days,
            'predecessor' => $this->whenLoaded('predecessor', fn () => new ProjetoMilestoneResource($dependency->predecessor)),
            'successor' => $this->whenLoaded('successor', fn () => new ProjetoMilestoneResource($dependency->successor)),
        ];
    }
}
