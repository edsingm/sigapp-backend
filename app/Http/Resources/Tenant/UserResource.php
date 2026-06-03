<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\UserResource as BaseUserResource;
use Illuminate\Http\Request;

class UserResource extends BaseUserResource
{
    /**
     * Transformar o recurso em um array.
     * Herda permissions, roles do Resource base e adiciona campos específicos de tenant.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = parent::toArray($request);

        return array_merge($base, [
            'department' => $this->whenLoaded('department', fn () => new DepartmentResource($this->department)),
            'department_id' => $this->department_id,
            'position' => $this->whenLoaded('position', fn () => new PositionResource($this->position)),
            'position_id' => $this->position_id,
            'phone' => $this->phone,
            'cpf' => $this->cpf,
            'rg' => $this->rg,
            'birth_date' => $this->birth_date,
            'gender' => $this->gender,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'zip_code' => $this->zip_code,
            'profile_picture' => $this->profile_picture,
            'status' => $this->status,
            'locale' => $this->locale ?? 'pt-br',
        ]);
    }
}
