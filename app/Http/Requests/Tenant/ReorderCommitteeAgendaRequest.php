<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ComiteRevisao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReorderCommitteeAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', ComiteRevisao::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['agenda_item_ids' => ['required', 'array', 'min:1'], 'agenda_item_ids.*' => ['required', 'integer', 'distinct']];
    }
}
