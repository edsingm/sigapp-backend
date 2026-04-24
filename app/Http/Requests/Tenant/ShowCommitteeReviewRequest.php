<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ComiteRevisao;
use Illuminate\Foundation\Http\FormRequest;

class ShowCommitteeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $reviewId = $this->route('comite') ?? $this->route('id');
        $review = $reviewId instanceof ComiteRevisao ? $reviewId : ComiteRevisao::find($reviewId);

        return $user !== null
            && $review instanceof ComiteRevisao
            && $user->can('view', $review);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
