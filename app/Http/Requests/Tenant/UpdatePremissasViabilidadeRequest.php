<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePremissasViabilidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => 'sometimes|required|string|max:255',
            'perfil_financiamento' => 'sometimes|required|string|in:cef,proprio',
            'ativo' => 'sometimes|boolean',
            'versao' => 'sometimes|integer|min:1',
            'vigente_em' => 'nullable|date',
            'encerrada_em' => 'nullable|date|after:vigente_em',
            'pis_cofins' => 'nullable|numeric|min:0|max:100',
            'iss' => 'nullable|numeric|min:0|max:100',
            'outros_impostos' => 'nullable|numeric|min:0|max:100',
            'comissao' => 'nullable|numeric|min:0|max:100',
            'parceria_vgv' => 'nullable|numeric|min:0',
            'infra_nao_incidente' => 'nullable|numeric|min:0|max:100',
            'incorporacao' => 'nullable|numeric|min:0|max:100',
            'incorp_ri' => 'nullable|numeric|min:0|max:100',
            'incorp_entrega' => 'nullable|numeric|min:0|max:100',
            'incorp_ate_lancamento' => 'nullable|numeric|min:0|max:100',
            'obra_ate_lancamento' => 'nullable|numeric|min:0|max:100',
            'area_comum' => 'nullable|numeric|min:0',
            'contrapartidas' => 'nullable|numeric|min:0|max:100',
            'canteiro_mensal' => 'nullable|numeric|min:0',
            'mo_administrativa' => 'nullable|numeric|min:0',
            'seguros' => 'nullable|numeric|min:0|max:100',
            'assistencia_tecnica' => 'nullable|numeric|min:0|max:100',
            'despesas_comerciais' => 'nullable|numeric|min:0|max:100',
            'stand_vendas' => 'nullable|numeric|min:0',
            'mobilia_decoracao' => 'nullable|numeric|min:0',
            'construcao_stand_meses_antes_lancamento' => 'nullable|integer|min:0|max:60',
            'ajuda_custo_gerente' => 'nullable|numeric|min:0',
            'ajuda_custo_gerente_regional' => 'nullable|numeric|min:0',
            'reembolso_logistica' => 'nullable|numeric|min:0',
            'bonus_cca' => 'nullable|numeric|min:0',
            'bonus_gerente' => 'nullable|numeric|min:0|max:100',
            'bonus_gerente_regional' => 'nullable|numeric|min:0|max:100',
            'bonus_credito' => 'nullable|numeric|min:0|max:100',
            'bonus_gestor_comercial' => 'nullable|numeric|min:0|max:100',
            'bonus_equipe_comercial' => 'nullable|numeric|min:0',
            'pagamento_comissao_desligamento' => 'nullable|numeric|min:0|max:100',
            'parcelamento_comissao_meses' => 'nullable|integer|min:1|max:120',
            'parcelamento_comissao_terreno' => 'nullable|integer|min:0',
            'marketing' => 'nullable|numeric|min:0|max:100',
            'marketing_inicio_antes_lancamento' => 'nullable|integer|min:0',
            'itbi_iptu' => 'nullable|numeric|min:0|max:100',
            'registro' => 'nullable|numeric|min:0',
            'custo_contratacao_cef' => 'nullable|numeric|min:0',
            'custo_medicao_cef' => 'nullable|numeric|min:0',
            'contratos_cef' => 'nullable|numeric|min:0',
            'produtos_cef' => 'nullable|numeric|min:0|max:100',
            'outras_despesas_financeiras' => 'nullable|numeric|min:0',
            'despesas_onerosas_bancos' => 'nullable|numeric|min:0|max:100',
            'prazo_obra' => 'nullable|integer|in:18,24,36,48,60',
            'compra_terreno' => 'nullable|numeric|min:0',
            'porcentagem_lote_proprietario' => 'nullable|numeric|min:0|max:100',
            'taxa_juros_pj' => 'nullable|numeric|min:0|max:100',
            'carencia_pj_meses' => 'nullable|integer|min:0',
            'amortizacao_pj_parcelas' => 'nullable|integer|min:0',
            'percentual_antecipacao_pj' => 'nullable|numeric|min:0|max:100',
            'aporte_adicional_mensal' => 'nullable|numeric|min:0',
            'devolucao_aporte_percentual' => 'nullable|numeric|min:0|max:100',
            'distribuicao_lucros_percentual_obra' => 'nullable|numeric|min:0|max:100',
            'taxa_exposicao_aplicada' => 'nullable|numeric|min:0|max:100',
            'avaliacao_lotes_cef' => 'nullable|array',
            'inadimplencia' => 'nullable|numeric|min:0|max:100',
            'atraso_meses' => 'nullable|integer|min:0',
            'taxa_perda' => 'nullable|numeric|min:0|max:100',
            'meses_incorporacao' => 'nullable|integer|min:1|max:60',
            'meses_lancamento' => 'nullable|integer|min:1|max:24',
            'meses_entrega' => 'nullable|integer|min:0',
            'meses_pos_obra' => 'nullable|integer|min:0',
            'variavel_correcao' => 'nullable|numeric|min:0',
        ];
    }
}
