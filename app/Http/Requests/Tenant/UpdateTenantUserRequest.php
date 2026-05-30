<?php

namespace App\Http\Requests\Tenant;

use App\Enums\Common\RolesEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateTenantUserRequest extends FormRequest
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
        $passwordRules = ['sometimes', Password::defaults()];

        /** @var list<ValidationRule|string> $emailRules */
        $emailRules = ['sometimes', 'email', Rule::unique('users', 'email')->ignore($this->route('id'))];

        /** @var list<ValidationRule|string> $roleRules */
        $roleRules = ['sometimes', 'string', Rule::in(array_column(RolesEnum::cases(), 'value'))];

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => $emailRules,
            'password' => $passwordRules,
            'role' => $roleRules,
        ];
    }
}
