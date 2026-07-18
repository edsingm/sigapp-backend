<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComiteRevisaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'decided_by_user' => $this->formatActorUser($this->resolveActorUser(
                $this->decided_by,
                'decidedBy',
            )),
            'terreno' => new TerrenoResource($this->whenLoaded('terreno')),
            'viabilidade' => new ViabilidadeResource($this->whenLoaded('viabilidade')),
            'pareceres_departamento' => ComiteParecerDepartamentoResource::collection($this->whenLoaded('pareceresDepartamento')),
            'pendencias' => ComitePendenciaResource::collection($this->whenLoaded('pendencias')),
        ];
    }

    private function resolveActorUser(mixed $userId, string $relation): ?User
    {
        if ($this->relationLoaded($relation)) {
            $loaded = $this->{$relation};
            if ($loaded instanceof User) {
                return $loaded;
            }
        }

        $id = is_numeric($userId) ? (int) $userId : null;
        if ($id === null || $id <= 0) {
            return null;
        }

        return User::query()
            ->with(['department', 'roles'])
            ->find($id);
    }

    /**
     * @return array{id: int, name: string, role: string|null, department: string|null}|null
     */
    private function formatActorUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        if (! $user->relationLoaded('roles')) {
            $user->load('roles');
        }
        if (! $user->relationLoaded('department')) {
            $user->load('department');
        }

        $roleName = $user->roles->first()?->name;
        $departmentName = $user->department?->name;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => is_string($roleName) ? $roleName : null,
            'department' => is_string($departmentName) ? $departmentName : null,
        ];
    }
}
