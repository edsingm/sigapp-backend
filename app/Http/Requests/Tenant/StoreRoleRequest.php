<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
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
            'name' => 'required|string|max:255|unique:roles,name',
            'guard_name' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];
    }

    /**
     * Obtém as mensagens personalizadas para erros do validador.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da role é obrigatório.',
            'name.unique' => 'Já existe uma role com este nome.',
            'name.max' => 'O nome da role não pode ter mais de 255 caracteres.',
            'permissions.array' => 'As permissões devem ser uma lista.',
            'permissions.*.exists' => 'Uma ou mais permissões selecionadas não existem.',
        ];
    }
}
