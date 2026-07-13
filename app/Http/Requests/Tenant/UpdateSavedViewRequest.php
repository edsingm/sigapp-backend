<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSavedViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'resource' => ['sometimes', 'string', 'max:100'],
            'scope' => ['sometimes', 'string', 'in:private,shared'],
            'filters' => ['sometimes', 'array'],
            'columns' => ['sometimes', 'array'],
            'sort' => ['sometimes', 'array'],
            'view_mode' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_default' => ['sometimes', 'boolean'],
            'shared_with_user_ids' => ['sometimes', 'nullable', 'array'],
            'shared_with_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
