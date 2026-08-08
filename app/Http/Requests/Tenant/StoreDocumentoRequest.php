<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Documento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Documento::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'terreno_id' => ['required', 'exists:terrenos,id'],
            // extensions valida o nome original; mimetypes inclui zip/xml porque
            // KMZ/Office OOXML e KML raramente reportam o MIME "de catálogo".
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
            'nome' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', Rule::in($this->documentTypes())],
            'categoria' => ['nullable', Rule::in($this->documentCategories())],
            'descricao' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terreno_id.required' => 'O terreno é obrigatório.',
            'terreno_id.exists' => 'O terreno informado não existe.',
            'arquivo.required' => 'O arquivo é obrigatório.',
            'arquivo.file' => 'O campo arquivo deve conter um arquivo válido.',
            'arquivo.max' => 'O arquivo não pode ser maior que 10 MB.',
            'arquivo.extensions' => 'Tipo de arquivo não permitido. Envie PDF, imagens, Office, KML/KMZ ou DWG.',
            'arquivo.mimetypes' => 'Tipo de arquivo não permitido. Envie PDF, imagens, Office, KML/KMZ ou DWG.',
            'tipo.in' => 'O tipo de documento informado é inválido.',
            'categoria.in' => 'A categoria informada é inválida.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',
            'descricao.max' => 'A descrição não pode ter mais de 1000 caracteres.',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function documentTypes(): array
    {
        return [
            'escritura',
            'matricula',
            'certidao_negativa',
            'iptu',
            'planta',
            'levantamento_topografico',
            'laudo_ambiental',
            'viabilidade',
            'contrato',
            'procuracao',
            'rg_cpf',
            'comprovante_residencia',
            'outros',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function documentCategories(): array
    {
        return [
            'juridico',
            'tecnico',
            'financeiro',
            'ambiental',
            'pessoal',
        ];
    }
}
