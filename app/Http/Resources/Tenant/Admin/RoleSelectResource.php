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
class RoleSelectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => RolesEnum::tryFrom($this->name)?->label() ?? $this->name,
        ];
    }
}
