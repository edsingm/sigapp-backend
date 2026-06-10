<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Documento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDocumentosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Documento::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'terreno_id' => ['nullable', 'integer', 'exists:terrenos,id'],
            'tipo' => ['nullable', 'string', Rule::in($this->documentTypes())],
            'categoria' => ['nullable', 'string', Rule::in($this->documentCategories())],
            'status' => ['nullable', 'string', Rule::in(['pendente', 'aprovado', 'rejeitado'])],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'string', Rule::in(['created_at', 'updated_at', 'nome', 'tipo', 'categoria', 'status', 'tamanho'])],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
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
