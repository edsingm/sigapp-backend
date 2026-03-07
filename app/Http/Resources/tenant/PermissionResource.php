<?php

namespace App\Http\Resources\tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Extrai módulo e ação do nome da permissão
        $parts = explode('.', $this->name);
        $module = $parts[0] ?? $this->name;
        $action = $parts[1] ?? null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'module' => $module,
            'action' => $action,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
