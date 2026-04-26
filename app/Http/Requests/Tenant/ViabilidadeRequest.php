<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Viabilidade;
use App\Models\Tenant\TerrenoProduto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ViabilidadeRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($this->isMethod('post')) {
            return $user->can('create', Viabilidade::class);
        }

        $viabilidadeId = $this->route('viabilidade') ?? $this->route('id');
        $viabilidade = $viabilidadeId instanceof Viabilidade ? $viabilidadeId : Viabilidade::find($viabilidadeId);

        return $viabilidade instanceof Viabilidade
            && $user->can('update', $viabilidade);
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'terreno_id' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'exists:terrenos,id',
                function ($attribute, $value, $fail) {
                    $hasProducts = TerrenoProduto::where('terreno_id', $value)->exists();
                    if (! $hasProducts) {
                        $fail('O terreno selecionado não possui produtos associados. Cadastre produtos antes de criar uma viabilidade.');
                    }
                },
            ],
            'parceria_vgv' => 'nullable|numeric|min:0',
            'compra_terreno' => 'nullable|numeric|min:0',
            'infra_nao_incidente' => 'nullable|numeric|min:0|max:100',
            'porcentagem_lote_proprietario' => 'nullable|numeric|min:0|max:100',
            'prazo_obra' => 'nullable|in:18,24,36,48,60',
            'prazo_lancamento' => 'nullable|integer|min:1|max:24',
            'prazo_incorporacao' => 'nullable|integer|min:1|max:60',
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
            'stand_vendas' => 'nullable|numeric|min:0',
            'mobilia_decoracao' => 'nullable|numeric|min:0',
            'ajuda_custo_gerente' => 'nullable|numeric|min:0',
            'ajuda_custo_gerente_regional' => 'nullable|numeric|min:0',
            'reembolso_logistica' => 'nullable|numeric|min:0',
            'bonus_cca' => 'nullable|numeric|min:0',
            'bonus_gerente' => 'nullable|numeric|min:0|max:100',
            'bonus_gerente_regional' => 'nullable|numeric|min:0|max:100',
            'bonus_credito' => 'nullable|numeric|min:0|max:100',
            'bonus_gestor_comercial' => 'nullable|numeric|min:0|max:100',
            'pagamento_comissao_desligamento' => 'nullable|numeric|min:0|max:100',
            'parcelamento_comissao_meses' => 'nullable|integer|min:1|max:120',
            'marketing' => 'nullable|numeric|min:0|max:100',
            'itbi_iptu' => 'nullable|numeric|min:0|max:100',
            'registro' => 'nullable|numeric|min:0',
            'contratos_cef' => 'nullable|numeric|min:0',
            'produtos_cef' => 'nullable|numeric|min:0|max:100',
            'outras_despesas_financeiras' => 'nullable|numeric|min:0|max:100',
            'despesas_onerosas_bancos' => 'nullable|numeric|min:0|max:100',
            'percentual_antecipacao_pj' => 'nullable|numeric|min:0|max:100',
            'aporte_adicional_mensal' => 'nullable|numeric|min:0',
            'devolucao_aporte_percentual' => 'nullable|numeric|min:0|max:100',
            'distribuicao_lucros_percentual_obra' => 'nullable|numeric|min:0|max:100',
            'taxa_exposicao_aplicada' => 'nullable|numeric|min:0|max:100',
            'perfil_financiamento' => 'nullable|string|in:cef,proprio',
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
            'prazo_lancamento' => 'prazo de lançamento',
            'prazo_incorporacao' => 'prazo de incorporação',
            'pis_cofins' => 'PIS/COFINS',
            'iss' => 'ISS',
            'outros_impostos' => 'outros impostos',
            'comissao' => 'comissão',
            'incorporacao' => 'incorporação',
            'incorporacao_ri' => 'incorporação RI',
            'incorporacao_entrega' => 'incorporação entrega',
            'incorporacao_ate_lancamento' => 'incorporação até lançamento',
            'area_comum' => 'área comum',
            'contrapartidas' => 'contrapartidas',
            'canteiro_mensal' => 'canteiro mensal',
            'mo_administrativa' => 'mão de obra administrativa',
            'seguros' => 'seguros',
            'assistencia_tecnica' => 'assistência técnica',
            'assistencia_tecnica_curva' => 'curva de assistência técnica',
            'despesas_comerciais' => 'despesas comerciais',
            'stand_vendas' => 'stand de vendas',
            'mobilia_decoracao' => 'mobília e decoração',
            'gastos_mensais_stand' => 'gastos mensais do stand',
            'comissao_house_percentual' => 'comissão house',
            'comissao_imobiliarias_percentual' => 'comissão imobiliárias',
            'percentual_vendas_house' => 'percentual de vendas house',
            'ajuda_custo_gerente' => 'ajuda de custo gerente',
            'ajuda_custo_gerente_regional' => 'ajuda de custo gerente regional',
            'reembolso_logistica' => 'reembolso de logística',
            'bonus_cca' => 'bônus CCA',
            'bonus_gerente' => 'bônus gerente',
            'bonus_gerente_regional' => 'bônus gerente regional',
            'bonus_credito' => 'bônus crédito',
            'bonus_gestor_comercial' => 'bônus gestor comercial',
            'pagamento_comissao_venda' => 'pagamento da comissão na venda',
            'pagamento_comissao_desligamento' => 'pagamento da comissão no desligamento',
            'parcelamento_comissao_meses' => 'parcelamento da comissão em meses',
            'marketing' => 'marketing',
            'marketing_lancamento' => 'marketing no lançamento',
            'marketing_inicio_antes_lancamento' => 'início do marketing antes do lançamento',
            'itbi_iptu' => 'ITBI/IPTU',
            'registro' => 'registro',
            'medicao_contratacao' => 'medição/contratação',
            'contratos_cef' => 'contratos CEF',
            'produtos_cef' => 'produtos CEF',
            'outras_despesas_financeiras' => 'outras despesas financeiras',
            'despesas_onerosas_bancos' => 'despesas onerosas bancos',
            'taxa_juros_pj' => 'taxa de juros PJ',
            'percentual_antecipacao_pj' => 'percentual de antecipação PJ',
            'carencia_pj_meses' => 'carência PJ em meses',
            'amortizacao_pj_parcelas' => 'amortização PJ em parcelas',
            'aporte_adicional_mensal' => 'aporte adicional mensal',
            'devolucao_aporte_percentual' => 'devolução de aporte percentual',
            'distribuicao_lucros_percentual_obra' => 'distribuição de lucros percentual obra',
            'taxa_exposicao_aplicada' => 'taxa de exposição aplicada',
            'perfil_financiamento' => 'perfil de financiamento',
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
