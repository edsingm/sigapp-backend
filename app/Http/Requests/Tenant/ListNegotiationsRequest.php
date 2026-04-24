<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Negociacao;
use Illuminate\Foundation\Http\FormRequest;

class ListNegotiationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', Negociacao::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
