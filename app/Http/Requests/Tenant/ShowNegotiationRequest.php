<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Negociacao;
use Illuminate\Foundation\Http\FormRequest;

class ShowNegotiationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $negotiationId = $this->route('negociacao') ?? $this->route('id');
        $negotiation = $negotiationId instanceof Negociacao ? $negotiationId : Negociacao::find($negotiationId);

        return $user !== null
            && $negotiation instanceof Negociacao
            && $user->can('view', $negotiation);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
