<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ComiteRevisao;
use Illuminate\Foundation\Http\FormRequest;

class UpsertCommitteeDepartmentReviewRequest extends FormRequest
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
            'department_code' => ['required', 'string'],
            'reviewer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'decision' => ['required', 'string', 'in:aprovado,aprovado_com_ressalvas,reprovado'],
            'comments' => ['nullable', 'string'],
            'checklist_completed' => ['nullable', 'boolean'],
        ];
    }
}
