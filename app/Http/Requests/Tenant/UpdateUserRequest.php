<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->user)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'position_id' => ['sometimes', 'required', 'integer', 'exists:positions,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
            'status' => ['nullable', 'string', 'in:Active,Inactive,Suspended'],
            'phone' => ['nullable', 'string', 'max:20'],
            'cpf' => ['nullable', 'string', 'max:14', Rule::unique('users')->ignore($this->user)],
        ];
    }
}
