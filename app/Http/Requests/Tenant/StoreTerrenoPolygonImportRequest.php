<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTerrenoPolygonImportRequest extends FormRequest
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
            'arquivos' => ['required', 'array', 'min:1', 'max:10'],
            'arquivos.*' => [
                'required',
                'file',
                'max:10240',
                'mimetypes:application/vnd.google-earth.kml+xml,application/vnd.google-earth.kmz,application/zip,application/xml,text/xml,text/plain,application/octet-stream',
                'extensions:kml,kmz',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'A chave de idempotência é obrigatória.',
            'idempotency_key.uuid' => 'A chave de idempotência deve ser um UUID válido.',
            'arquivos.required' => 'Envie ao menos um arquivo KML ou KMZ.',
            'arquivos.array' => 'Os arquivos devem ser enviados como lista.',
            'arquivos.min' => 'Envie ao menos um arquivo KML ou KMZ.',
            'arquivos.max' => 'Envie no máximo 10 arquivos por importação.',
            'arquivos.*.required' => 'Cada item deve ser um arquivo válido.',
            'arquivos.*.file' => 'Cada item deve ser um arquivo válido.',
            'arquivos.*.max' => 'Cada arquivo não pode ser maior que 10 MB.',
            'arquivos.*.mimetypes' => 'Envie somente arquivos .kml ou .kmz.',
            'arquivos.*.extensions' => 'Envie somente arquivos .kml ou .kmz.',
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $total = 0;
                $files = $this->file('arquivos', []);
                if (! is_array($files)) {
                    return;
                }
                foreach ($files as $file) {
                    $total += (int) $file->getSize();
                }
                if ($total > (40 * 1024 * 1024)) {
                    $validator->errors()->add('arquivos', 'O total dos arquivos não pode exceder 40 MB.');
                }
            },
        ];
    }
}
