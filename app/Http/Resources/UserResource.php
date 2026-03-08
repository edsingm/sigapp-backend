<?php

namespace App\Http\Resources;

use App\Services\ModuleAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $permissions = $this->getAllPermissions()->pluck('name');

        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'email_verified_at'  => $this->email_verified_at?->toIso8601String(),
            'role'               => $this->roles->first()?->name,
            'roles'              => $this->roles->pluck('name'),
            'permissions'        => $permissions,
            'module_permissions' => app(ModuleAccessService::class)->resolveModuleAccess($permissions->all()),
            'created_at'         => $this->created_at->toIso8601String(),
            'updated_at'         => $this->updated_at->toIso8601String(),
        ];
    }
}
