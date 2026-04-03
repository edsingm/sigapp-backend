<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Services\ApiResponseService;

class SignupRequest extends FormRequest
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
            'plan_slug' => ['required', 'string', 'exists:plans,slug'],
            'organization_name' => ['required', 'string', 'min:3', 'max:255'],
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', // apenas kebab-case
            ],
            'admin_name' => ['required', 'string', 'min:3', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', // Pelo menos uma letra minúscula, uma maiúscula e um dígito
            ],
            'accept_usage_contract' => ['accepted'],
        ];
    }

    /**
     * Obtém as mensagens personalizadas para erros do validador.
     */
    public function messages(): array
    {
        return [
            'plan_slug.required' => 'O plano é obrigatório',
            'plan_slug.exists' => 'Plano selecionado não existe',
            'organization_name.required' => 'O nome da organização é obrigatório',
            'organization_name.min' => 'O nome deve ter ao menos 3 caracteres',
            'slug.required' => 'O slug é obrigatório',
            'slug.regex' => 'O slug deve conter apenas letras minúsculas, números e hífens',
            'slug.min' => 'O slug deve ter ao menos 3 caracteres',
            'admin_email.required' => 'O email do administrador é obrigatório',
            'admin_email.email' => 'Email inválido',
            'admin_password.required' => 'A senha é obrigatória',
            'admin_password.min' => 'A senha deve ter ao menos 8 caracteres',
            'admin_password.regex' => 'A senha deve conter letras maiúsculas, minúsculas e números',
            'accept_usage_contract.accepted' => 'Você deve aceitar o Contrato de Utilização para continuar',
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
