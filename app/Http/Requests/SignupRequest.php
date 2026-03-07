<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Services\ApiResponseService;

class SignupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
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
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', // kebab-case only
            ],
            'admin_name' => ['required', 'string', 'min:3', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', // At least one lowercase, uppercase, and digit
            ],
            'accept_usage_contract' => ['accepted'],
        ];
    }

    /**
     * Get custom messages for validator errors.
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
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponseService::validationError($validator->errors()->toArray())
        );
    }
}
