<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComiteParecerDepartamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department_code' => $this->department_code,
            'reviewer_user_id' => $this->reviewer_user_id,
            'decision' => $this->decision,
            'comments' => $this->comments,
            'checklist_completed' => $this->checklist_completed,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'reviewer' => $this->formatActorUser($this->resolveActorUser(
                $this->reviewer_user_id,
                'reviewer',
            )),
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
