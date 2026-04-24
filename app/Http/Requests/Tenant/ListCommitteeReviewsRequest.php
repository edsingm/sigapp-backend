<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ComiteRevisao;
use Illuminate\Foundation\Http\FormRequest;

class ListCommitteeReviewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', ComiteRevisao::class);
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
