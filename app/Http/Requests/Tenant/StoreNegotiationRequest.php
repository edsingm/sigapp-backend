<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Negociacao;
use Illuminate\Foundation\Http\FormRequest;

class StoreNegotiationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Negociacao::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'terreno_id' => ['required', 'integer', 'exists:terrenos,id'],
            'status' => ['nullable', 'string'],
            'proposal_value' => ['nullable', 'numeric'],
            'business_model' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
