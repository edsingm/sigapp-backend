<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;

class StoreTerrenoImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Terreno::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'arquivo' => [
                'required',
                'file',
                'max:10240',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip',
                'extensions:xlsx',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'A chave de idempotência é obrigatória.',
            'idempotency_key.uuid' => 'A chave de idempotência deve ser um UUID válido.',
            'arquivo.required' => 'O arquivo é obrigatório.',
            'arquivo.file' => 'O campo arquivo deve conter um arquivo válido.',
            'arquivo.max' => 'A planilha não pode ser maior que 10 MB.',
            'arquivo.mimetypes' => 'Envie uma planilha Excel (.xlsx).',
            'arquivo.extensions' => 'Envie uma planilha Excel (.xlsx).',
        ];
    }
}
