<?php

namespace App\Http\Requests\Tenant;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class),
                function ($attribute, $value, $fail) {
                    if (! str_ends_with($value, '@lrgconstrutora.com.br')) {
                        $fail('O e-mail deve ser um endereço institucional @lrgconstrutora.com.br.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
            'status' => ['nullable', 'string', 'in:Active,Inactive,Suspended'],
            'phone' => ['nullable', 'string', 'max:20'],
            'cpf' => ['nullable', 'string', 'max:14', 'unique:users'],
        ];
    }
}
