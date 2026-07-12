<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ComiteRevisao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CommitteeAgendaItemRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'decision_required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:pending,in_discussion,decided,skipped'],
        ];
    }
}
