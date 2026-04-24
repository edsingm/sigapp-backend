<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ComiteRevisao;
use Illuminate\Foundation\Http\FormRequest;

class FinalizeCommitteeDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $reviewId = $this->route('comite') ?? $this->route('id');
        $review = $reviewId instanceof ComiteRevisao ? $reviewId : ComiteRevisao::find($reviewId);

        return $user !== null
            && $review instanceof ComiteRevisao
            && $user->can('update', $review);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'final_decision' => ['required', 'string', 'in:aprovado_comite,aprovado_com_ressalvas,reprovado_comite'],
            'final_comments' => ['nullable', 'string'],
            'pendencias' => ['nullable', 'array'],
            'pendencias.*.title' => ['required_with:pendencias', 'string'],
            'pendencias.*.description' => ['nullable', 'string'],
            'pendencias.*.severity' => ['nullable', 'string'],
            'pendencias.*.department_code' => ['nullable', 'string'],
            'pendencias.*.responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'pendencias.*.due_date' => ['nullable', 'date'],
        ];
    }
}
