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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
