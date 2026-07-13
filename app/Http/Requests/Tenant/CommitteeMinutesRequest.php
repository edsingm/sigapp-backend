<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ComiteRevisao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CommitteeMinutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', ComiteRevisao::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'summary' => ['nullable', 'string'],
            'decisions' => ['nullable', 'array'],
            'blockers' => ['nullable', 'array'],
            'next_steps' => ['nullable', 'string'],
            'approved' => ['sometimes', 'boolean'],
        ];
    }
}
