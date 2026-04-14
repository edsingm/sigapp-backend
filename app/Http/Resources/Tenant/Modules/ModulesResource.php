<?php

namespace App\Http\Resources\Tenant\Modules;

use App\Enums\Common\ModulesEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModulesResource extends JsonResource
{
    /**
     * Serializa um único módulo para a API.
     * Não expõe campos internos (id, created_at, updated_at).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $submoduleEnums = ModulesEnum::from($this->slug)->submodules();

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'icon' => $this->icon,
            'description' => $this->description,
            'order' => (int) $this->order,
            'active' => (bool) $this->active,
            'submodules' => array_map(
                fn ($sub) => ['slug' => $sub->value, 'label' => $sub->label()],
                $submoduleEnums
            ),
        ];
    }
}
