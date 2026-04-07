<?php

namespace App\Http\Resources;

use App\Enums\Common\ModulesEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CentralUserResource extends JsonResource
{
    /**
     * Transformar o recurso em um array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_admin' => (bool) $this->is_admin,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'role' => $this->is_admin ? 'sigapp' : null,
            'roles' => $this->is_admin ? ['sigapp'] : [],
            'permissions' => $this->is_admin ? $this->allModulesAsManager() : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Retorna todos os módulos no nível de gerente como um array simples de strings de permissão.
     * Usado para usuários administradores do SIGAPP que possuem acesso irrestrito.
     */
    private function allModulesAsManager(): array
    {
        $permissions = [];

        foreach (ModulesEnum::cases() as $module) {
            if ($module->hasSubmodules()) {
                foreach ($module->submodules() as $resource) {
                    $permissions[] = "{$module->value}.{$resource}.viewer";
                    $permissions[] = "{$module->value}.{$resource}.editor";
                    $permissions[] = "{$module->value}.{$resource}.manager";
                }
            } else {
                $permissions[] = "{$module->value}.viewer";
                $permissions[] = "{$module->value}.editor";
                $permissions[] = "{$module->value}.manager";
            }
        }

        return $permissions;
    }
}
