<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Viabilidade;
use Illuminate\Foundation\Http\FormRequest;

class SubmitViabilidadeApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $viabilidadeId = $this->route('viabilidade') ?? $this->route('id');
        $viabilidade = $viabilidadeId instanceof Viabilidade ? $viabilidadeId : Viabilidade::find($viabilidadeId);

        return $user !== null
            && $viabilidade instanceof Viabilidade
            && $user->can('requestApproval', $viabilidade);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'approval_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
