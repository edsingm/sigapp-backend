<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListAdminLoginAttemptsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'successful' => ['sometimes', 'nullable', 'boolean'],
            'stage' => ['sometimes', 'nullable', 'in:password,mfa,recovery'],
            'email' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ip' => ['sometimes', 'nullable', 'string', 'max:45'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('successful') && $this->input('successful') !== null && $this->input('successful') !== '') {
            $raw = $this->input('successful');
            if (is_string($raw)) {
                $this->merge([
                    'successful' => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]);
            }
        }
    }
}
