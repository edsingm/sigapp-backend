<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ComiteParecerDepartamento;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComiteParecerDepartamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $departmentReview = $this->resource;
        $reviewer = $departmentReview instanceof ComiteParecerDepartamento
            && $departmentReview->relationLoaded('reviewer')
                ? $departmentReview->getRelation('reviewer')
                : null;

        return [
            'id' => $this->id,
            'department_code' => $this->department_code,
            'reviewer_user_id' => $this->reviewer_user_id,
            'decision' => $this->decision,
            'comments' => $this->comments,
            'checklist_completed' => $this->checklist_completed,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'reviewer' => $this->formatActorUser(
                $reviewer instanceof User ? $reviewer : null,
            ),
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
