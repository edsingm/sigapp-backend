<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavedViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'resource' => ['required', 'string', 'max:100'],
            'scope' => ['sometimes', 'string', 'in:private,shared'],
            'filters' => ['nullable', 'array'],
            'columns' => ['nullable', 'array'],
            'sort' => ['nullable', 'array'],
            'view_mode' => ['nullable', 'string', 'max:50'],
            'is_default' => ['sometimes', 'boolean'],
            'shared_with_user_ids' => ['nullable', 'array'],
            'shared_with_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
