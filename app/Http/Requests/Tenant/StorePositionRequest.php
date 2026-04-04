<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'level'       => ['required', 'integer', 'min:1', 'max:9999'],
            'active'      => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'  => 'The position name is required.',
            'name.max'       => 'The position name must not exceed 150 characters.',
            'level.required' => 'The position hierarchy level is required.',
            'level.integer'  => 'The level must be an integer.',
            'level.min'      => 'The level must be at least 1.',
        ];
    }
}
