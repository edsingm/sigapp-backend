<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Negociacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class NegotiationApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', Negociacao::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'area' => ['required', 'string', 'max:100'],
            'decision' => ['sometimes', 'string', 'in:pending,approved,rejected'],
            'comment' => ['nullable', 'string'],
        ];
    }
}
