<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Admin;

use App\Enums\Common\RolesEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'permissions_count' => $this->whenCounted('permissions'),
            'users_count' => $this->when(isset($this->users_count), fn () => $this->users_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public static function withUsersCount(Role $role, int $usersCount): self
    {
        $resource = new self($role);
        $role->users_count = $usersCount;

        return $resource;
    }
}
