<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ComiteRevisao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CommitteeParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', ComiteRevisao::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::requiredIf(fn (): bool => ! $this->filled('email'))],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['sometimes', 'string', 'in:chair,participant,observer,guest'],
            'attendance_status' => ['sometimes', 'string', 'in:invited,confirmed,attended,absent'],
            'joined_at' => ['nullable', 'date'],
        ];
    }
}
