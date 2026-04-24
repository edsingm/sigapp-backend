<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Contrato;
use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Contrato::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'terreno_id' => ['required', 'integer', 'exists:terrenos,id'],
            'negociacao_id' => ['nullable', 'integer', 'exists:negociacoes,id'],
            'contract_type' => ['nullable', 'string'],
            'contract_number' => ['nullable', 'string'],
            'signed_at' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
            'file_path' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'partes' => ['nullable', 'array'],
            'partes.*.name' => ['required_with:partes', 'string'],
            'partes.*.document' => ['nullable', 'string'],
            'partes.*.party_type' => ['nullable', 'string'],
            'partes.*.signer_name' => ['nullable', 'string'],
            'partes.*.signer_document' => ['nullable', 'string'],
        ];
    }
}
