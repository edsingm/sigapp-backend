<?php

namespace App\Http\Requests\Tenant;

use App\Enums\Common\RolesEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasAnyRole(['admin', 'ADMIN', 'director', 'DIRECTOR']);
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var list<ValidationRule|string> $passwordRules */
        $passwordRules = ['required', Password::defaults()];

        /** @var list<ValidationRule|string> $roleRules */
        $roleRules = ['sometimes', 'string', Rule::in(array_column(RolesEnum::cases(), 'value'))];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => $passwordRules,
            'role' => $roleRules,
        ];
    }
}
