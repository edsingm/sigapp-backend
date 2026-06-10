<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Documento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Documento::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'string', 'max:255'],
            'tipo' => ['sometimes', Rule::in($this->documentTypes())],
            'categoria' => ['sometimes', 'nullable', Rule::in($this->documentCategories())],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::in(['pendente', 'aprovado', 'rejeitado'])],
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
