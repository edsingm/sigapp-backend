<?php

namespace App\Http\Requests;

use App\Services\ApiResponseService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
            'tenant_identifier' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * Obtém as mensagens personalizadas para erros do validador.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'O email é obrigatório',
            'email.email' => 'Email inválido',
            'password.required' => 'A senha é obrigatória',
        ];
    }

    /**
     * Manipula uma tentativa de validação com falha.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponseService::validationError($validator->errors()->toArray())
        );
    }
}
