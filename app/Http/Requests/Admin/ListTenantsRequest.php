<?php

namespace App\Http\Requests\Admin;

use App\Models\Central\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTenantsRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'all',
                    Tenant::STATUS_PENDING,
                    Tenant::STATUS_ACTIVE,
                    Tenant::STATUS_SUSPENDED,
                    Tenant::STATUS_CANCELLED,
                ]),
            ],
            'plan_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'on_trial' => ['sometimes', 'nullable', 'boolean'],
            'setup' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(['all', 'complete', 'incomplete']),
            ],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('on_trial') && $this->input('on_trial') !== null && $this->input('on_trial') !== '') {
            $raw = $this->input('on_trial');
            if (is_string($raw)) {
                $this->merge([
                    'on_trial' => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]);
            }
        }
    }
}
