<?php

namespace App\Http\Requests\Tenant\Admin;

use App\Enums\Common\RolesEnum;
use Illuminate\Foundation\Http\FormRequest;

class DestroyUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::DIRECTOR->value]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
