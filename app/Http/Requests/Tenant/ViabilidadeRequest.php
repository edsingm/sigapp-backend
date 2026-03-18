<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ViabilidadeRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'terreno_id' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'exists:terrenos,id',
                function ($attribute, $value, $fail) {
                    $hasProducts = \App\Models\Tenant\TerrenoProduto::where('terreno_id', $value)->exists();
                    if (!$hasProducts) {
                        $fail('O terreno selecionado não possui produtos associados. Cadastre produtos antes de criar uma viabilidade.');
                    }
                },
            ],
            'parceria_vgv' => 'nullable|numeric|min:0',
            'compra_terreno' => 'nullable|numeric|min:0',
            'infra_nao_incidente' => 'nullable|numeric|min:0|max:100',
            'porcentagem_lote_proprietario' => 'nullable|numeric|min:0|max:100',
            'prazo_obra' => 'nullable|in:18,24,36,48,60',
            'pis_cofins' => 'nullable|numeric|min:0|max:100',
            'iss' => 'nullable|numeric|min:0|max:100',
            'outros_impostos' => 'nullable|numeric|min:0|max:100',
            'comissao' => 'nullable|numeric|min:0|max:100',
            'incorporacao' => 'nullable|numeric|min:0|max:100',
            'area_comum' => 'nullable|numeric|min:0',
            'contrapartidas' => 'nullable|numeric|min:0|max:100',
            'canteiro_mensal' => 'nullable|numeric|min:0',
            'mo_administrativa' => 'nullable|numeric|min:0',
            'seguros' => 'nullable|numeric|min:0|max:100',
            'assistencia_tecnica' => 'nullable|numeric|min:0|max:100',
            'despesas_comerciais' => 'nullable|numeric|min:0|max:100',
            'marketing' => 'nullable|numeric|min:0|max:100',
            'itbi_iptu' => 'nullable|numeric|min:0|max:100',
            'registro' => 'nullable|numeric|min:0',
            'medicao_contratacao' => 'nullable|numeric|min:0',
            'contratos_cef' => 'nullable|numeric|min:0',
            'produtos_cef' => 'nullable|numeric|min:0|max:100',
            'outras_despesas_financeiras' => 'nullable|numeric|min:0|max:100',
            'despesas_onerosas_bancos' => 'nullable|numeric|min:0|max:100',
            'produtos' => 'nullable|array',
            'produtos.*.id' => 'required|exists:terreno_produtos,id',
            'produtos.*.unidades' => 'required|numeric|min:0',
            'produtos.*.valor' => 'required|numeric|min:0',
            'produtos.*.permuta' => 'required|numeric|min:0',
            'produtos.*.pgto_por_lote' => 'required|numeric|min:0',
            'produtos.*.custo_m2' => 'required|numeric|min:0',
            'produtos.*.custo_infra' => 'required|numeric|min:0',
        ];
    }

    /**
     * Obtém os atributos personalizados para erros do validador.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'terreno_id' => 'terreno',
            'parceria_vgv' => 'parceria VGV',
            'compra_terreno' => 'compra do terreno',
            'infra_nao_incidente' => 'infraestrutura não incidente',
            'porcentagem_lote_proprietario' => 'porcentagem lote proprietário',
            'prazo_obra' => 'prazo da obra',
            'pis_cofins' => 'PIS/COFINS',
            'iss' => 'ISS',
            'outros_impostos' => 'outros impostos',
            'comissao' => 'comissão',
            'incorporacao' => 'incorporação',
            'area_comum' => 'área comum',
            'contrapartidas' => 'contrapartidas',
            'canteiro_mensal' => 'canteiro mensal',
            'mo_administrativa' => 'mão de obra administrativa',
            'seguros' => 'seguros',
            'assistencia_tecnica' => 'assistência técnica',
            'despesas_comerciais' => 'despesas comerciais',
            'marketing' => 'marketing',
            'itbi_iptu' => 'ITBI/IPTU',
            'registro' => 'registro',
            'medicao_contratacao' => 'medição/contratação',
            'contratos_cef' => 'contratos CEF',
            'produtos_cef' => 'produtos CEF',
            'outras_despesas_financeiras' => 'outras despesas financeiras',
            'despesas_onerosas_bancos' => 'despesas onerosas bancos',
        ];
    }

    /**
     * Obtém as mensagens personalizadas para erros do validador.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terreno_id.required' => 'O campo :attribute é obrigatório.',
            'terreno_id.exists' => 'O :attribute selecionado não existe.',
            'prazo_obra.in' => 'O :attribute deve ser 18, 24, 36, 48 ou 60 meses.',
            '*.numeric' => 'O campo :attribute deve ser um número.',
            '*.min' => 'O campo :attribute deve ser maior ou igual a :min.',
            '*.max' => 'O campo :attribute deve ser menor ou igual a :max.',
        ];
    }

    /**
     * Prepara os dados para validação.
     */
    protected function prepareForValidation(): void
    {
        // Converter strings vazias para null
        $input = $this->all();
        
        foreach ($input as $key => $value) {
            if ($value === '' || $value === 'null') {
                $input[$key] = null;
            }
        }
        
        $this->replace($input);
    }
}
