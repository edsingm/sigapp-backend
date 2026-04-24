<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Contrato;
use Illuminate\Foundation\Http\FormRequest;

class SignContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $contractId = $this->route('contrato') ?? $this->route('id');
        $contract = $contractId instanceof Contrato ? $contractId : Contrato::find($contractId);

        return $user !== null
            && $contract instanceof Contrato
            && $user->can('update', $contract);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
