<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'tenant_identifier' => ['sometimes', 'string'],
            'intent' => ['sometimes', 'nullable', 'in:invite'],
            'accept_legal_documents' => ['exclude_unless:intent,invite', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accept_legal_documents.required_if' => language()->t('LEGAL_ACCEPTANCE_REQUIRED'),
            'accept_legal_documents.accepted' => language()->t('LEGAL_ACCEPTANCE_REQUIRED'),
        ];
    }
}
