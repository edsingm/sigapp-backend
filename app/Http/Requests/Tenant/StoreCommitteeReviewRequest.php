<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ComiteRevisao;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommitteeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', ComiteRevisao::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'terreno_id' => ['required', 'integer', 'exists:terrenos,id'],
            'viabilidade_id' => ['nullable', 'integer', 'exists:viabilidades,id'],
            'status' => ['nullable', 'string'],
            'required_departments' => ['nullable', 'array'],
            'required_departments.*' => ['string'],
        ];
    }
}
