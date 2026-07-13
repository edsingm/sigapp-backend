<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Projeto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ProjetoRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', Projeto::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'probability' => ['sometimes', 'string', 'in:low,medium,high'],
            'impact' => ['sometimes', 'string', 'in:low,medium,high'],
            'severity' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'status' => ['sometimes', 'string', 'in:open,mitigated,accepted,closed'],
            'mitigation' => ['nullable', 'string'],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
