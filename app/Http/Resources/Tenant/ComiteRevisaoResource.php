<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComiteRevisaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $review = $this->resource;
        $decidedBy = $review instanceof ComiteRevisao && $review->relationLoaded('decidedBy')
            ? $review->getRelation('decidedBy')
            : null;

        return [
            'id' => $this->id,
            'terreno_id' => $this->terreno_id,
            'viabilidade_id' => $this->viabilidade_id,
            'status' => $this->status,
            'final_decision' => $this->final_decision,
            'final_comments' => $this->final_comments,
            'required_departments' => $this->required_departments ?? [],
            'decided_by' => $this->decided_by,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'decided_by_user' => $this->formatActorUser(
                $decidedBy instanceof User ? $decidedBy : null,
            ),
            'terreno' => new TerrenoResource($this->whenLoaded('terreno')),
            'viabilidade' => new ViabilidadeResource($this->whenLoaded('viabilidade')),
            'pareceres_departamento' => ComiteParecerDepartamentoResource::collection($this->whenLoaded('pareceresDepartamento')),
            'pendencias' => ComitePendenciaResource::collection($this->whenLoaded('pendencias')),
        ];
    }

    /**
     * @return array{id: int, name: string, role: string|null, department: string|null}|null
     */
    private function formatActorUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $roleName = $user->relationLoaded('roles') ? $user->roles->first()?->name : null;
        $departmentName = $user->relationLoaded('department') ? $user->department?->name : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => is_string($roleName) ? $roleName : null,
            'department' => is_string($departmentName) ? $departmentName : null,
        ];
    }
}
