<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Documento;
use Illuminate\Foundation\Http\FormRequest;

class DocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Documento::find($this->route('documento'))) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Ver StoreDocumentoRequest: mimes do Laravel rejeita KMZ/Office (conteúdo ZIP).
            'arquivo' => [
                'required',
                'file',
                'max:10240',
                'extensions:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx,kml,kmz,dwg',
                'mimetypes:'.implode(',', [
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'application/vnd.google-earth.kml+xml',
                    'application/vnd.google-earth.kmz',
                    'application/zip',
                    'application/xml',
                    'text/xml',
                    'text/plain',
                    'application/octet-stream',
                    'image/vnd.dwg',
                    'application/acad',
                    'application/x-dwg',
                ]),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'arquivo.required' => 'O arquivo é obrigatório.',
            'arquivo.file' => 'O campo arquivo deve conter um arquivo válido.',
            'arquivo.max' => 'O arquivo não pode ser maior que 10 MB.',
            'arquivo.extensions' => 'Tipo de arquivo não permitido. Envie PDF, imagens, Office, KML/KMZ ou DWG.',
            'arquivo.mimetypes' => 'Tipo de arquivo não permitido. Envie PDF, imagens, Office, KML/KMZ ou DWG.',
        ];
    }
}
