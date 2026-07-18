<?php

namespace App\Http\Requests\Tenant;

use App\Enums\Common\RolesEnum;
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
        $user = $this->user();

        return $user !== null && $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::DIRECTOR->value]);
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
            'email' => [
                'sometimes',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'role' => ['sometimes', 'nullable', 'string', Rule::exists('roles', 'name')],
            'status' => ['nullable', 'string', 'in:Active,Inactive,Suspended'],
            'phone' => ['nullable', 'string', 'max:20'],
            'cpf' => [
                'nullable',
                'string',
                'max:14',
                Rule::unique('users', 'cpf')->ignore($this->route('user')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Já existe um usuário com este e-mail neste tenant.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'A confirmação de senha não confere.',
            'department_id.required' => 'Selecione um departamento.',
            'department_id.exists' => 'Departamento inválido.',
            'role.exists' => 'Cargo inválido.',
            'status.in' => 'Status inválido.',
            'cpf.unique' => 'Já existe um usuário com este CPF neste tenant.',
        ];
    }
}
