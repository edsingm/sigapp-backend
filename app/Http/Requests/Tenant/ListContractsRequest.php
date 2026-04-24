<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Contrato;
use Illuminate\Foundation\Http\FormRequest;

class ListContractsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', Contrato::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
