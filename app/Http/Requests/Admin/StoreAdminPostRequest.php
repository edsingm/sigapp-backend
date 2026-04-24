<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminPostRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'category' => ['nullable', 'string'],
            'image' => ['nullable', 'string'],
            'read_time' => ['nullable', 'string'],
            'featured' => ['sometimes', 'boolean'],
            'published' => ['sometimes', 'boolean'],
        ];
    }
}
