<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transformar o recurso em um array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'cpf'              => $this->cpf,
            'rg'               => $this->rg,
            'birth_date'       => $this->birth_date,
            'gender'           => $this->gender,
            'address'          => $this->address,
            'city'             => $this->city,
            'state'            => $this->state,
            'country'          => $this->country,
            'zip_code'         => $this->zip_code,
            'profile_picture'  => $this->profile_picture,
            'status'           => $this->status,
            'locale'            => $this->locale ?? 'pt-br',
            'department'        => $this->whenLoaded('department', fn() => new DepartmentResource($this->department)),
            'department_id'     => $this->department_id,
            'position'          => $this->whenLoaded('position', fn() => new PositionResource($this->position)),
            'position_id'       => $this->position_id,
            'email_verified_at' => $this->email_verified_at,
            'roles'            => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn($role) => [
                    'id'   => $role->id,
                    'name' => $role->name,
                ]);
            }),
            'roles_list'       => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')->toArray()),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
