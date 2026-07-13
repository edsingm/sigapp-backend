<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ListSavedViewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'resource' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', 'string', 'in:private,shared'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
