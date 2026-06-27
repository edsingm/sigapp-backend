<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ExportChecklistPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('export', Terreno::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string', 'max:5000'],
            'checklist' => ['nullable', 'array'],
            'responsavel' => ['nullable', 'string', 'max:255'],
            'data' => ['nullable', 'date'],
        ];
    }
}
