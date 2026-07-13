<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Negociacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class NegotiationOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', Negociacao::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'offer_type' => ['sometimes', 'string', 'in:proposal,counterproposal'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'business_model' => ['nullable', 'string', 'max:255'],
            'terms' => ['nullable', 'array'],
            'status' => ['sometimes', 'string', 'in:draft,submitted,withdrawn'],
            'valid_until' => ['nullable', 'date'],
            'previous_offer_id' => ['nullable', 'integer', 'exists:negociacao_ofertas,id'],
        ];
    }
}
