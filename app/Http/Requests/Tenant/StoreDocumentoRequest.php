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
            'arquivo' => [
                'required',
                'file',
                'max:3072',
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,ppt,pptx,kml,kmz,dwg',
            ],
            'nome' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', Rule::in($this->documentTypes())],
            'categoria' => ['nullable', Rule::in($this->documentCategories())],
            'descricao' => ['nullable', 'string', 'max:1000'],
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
