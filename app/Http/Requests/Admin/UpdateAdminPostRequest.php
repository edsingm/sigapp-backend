<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'excerpt' => ['sometimes', 'nullable', 'string'],
            'content' => ['sometimes', 'string'],
            'category' => ['sometimes', 'nullable', 'string'],
            'image' => ['sometimes', 'nullable', 'string'],
            'read_time' => ['sometimes', 'nullable', 'string'],
            'featured' => ['sometimes', 'boolean'],
            'published' => ['sometimes', 'boolean'],
        ];
    }
}
