<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Contrato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ContractConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', Contrato::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', 'in:pending,fulfilled,waived,blocked'],
            'evidence_document_id' => ['nullable', 'integer'],
        ];
    }
}
