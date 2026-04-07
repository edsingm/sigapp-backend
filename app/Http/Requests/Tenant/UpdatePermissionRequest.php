<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')->ignore($this->route('permission')),
            ],
            'guard_name' => 'nullable|string|max:255',
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
            'name.required' => 'O nome da permissão é obrigatório.',
            'name.unique' => 'Já existe uma permissão com este nome.',
            'name.max' => 'O nome da permissão não pode ter mais de 255 caracteres.',
        ];
    }
}
