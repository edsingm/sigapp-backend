<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Negociacao;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNegotiationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $negotiationId = $this->route('negociacao') ?? $this->route('id');
        $negotiation = $negotiationId instanceof Negociacao ? $negotiationId : Negociacao::find($negotiationId);

        return $user !== null
            && $negotiation instanceof Negociacao
            && $user->can('update', $negotiation);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string'],
            'proposal_value' => ['nullable', 'numeric'],
            'business_model' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
