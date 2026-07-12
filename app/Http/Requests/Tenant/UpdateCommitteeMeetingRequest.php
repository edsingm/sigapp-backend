<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ComiteRevisao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateCommitteeMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', ComiteRevisao::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'scheduled_at' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', 'in:draft,scheduled,in_progress,closed,cancelled'],
            'meeting_mode' => ['sometimes', 'string', 'in:online,in_person,hybrid'],
            'location' => ['nullable', 'string', 'max:255'],
            'chair_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
