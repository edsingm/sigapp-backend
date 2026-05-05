<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PremissasViabilidadeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'perfil_financiamento' => $this->perfil_financiamento instanceof \App\Enums\PerfilFinanciamento
                ? $this->perfil_financiamento->value
                : $this->perfil_financiamento,
            'ativo' => $this->ativo,
            'versao' => $this->versao,
            'vigente_em' => $this->vigente_em?->format('Y-m-d'),
            'encerrada_em' => $this->encerrada_em?->format('Y-m-d'),
            'pis_cofins' => $this->pis_cofins,
            'iss' => $this->iss,
            'outros_impostos' => $this->outros_impostos,
            'comissao' => $this->comissao,
            'parceria_vgv' => $this->parceria_vgv,
            'infra_nao_incidente' => $this->infra_nao_incidente,
            'incorporacao' => $this->incorporacao,
            'incorp_ri' => $this->incorp_ri,
            'incorp_entrega' => $this->incorp_entrega,
            'incorp_ate_lancamento' => $this->incorp_ate_lancamento,
            'obra_ate_lancamento' => $this->obra_ate_lancamento,
            'area_comum' => $this->area_comum,
            'contrapartidas' => $this->contrapartidas,
            'canteiro_mensal' => $this->canteiro_mensal,
            'mo_administrativa' => $this->mo_administrativa,
            'seguros' => $this->seguros,
            'assistencia_tecnica' => $this->assistencia_tecnica,
            'despesas_comerciais' => $this->despesas_comerciais,
            'stand_vendas' => $this->stand_vendas,
            'mobilia_decoracao' => $this->mobilia_decoracao,
            'construcao_stand_meses_antes_lancamento' => $this->construcao_stand_meses_antes_lancamento,
            'ajuda_custo_gerente' => $this->ajuda_custo_gerente,
            'ajuda_custo_gerente_regional' => $this->ajuda_custo_gerente_regional,
            'reembolso_logistica' => $this->reembolso_logistica,
            'bonus_cca' => $this->bonus_cca,
            'bonus_gerente' => $this->bonus_gerente,
            'bonus_gerente_regional' => $this->bonus_gerente_regional,
            'bonus_credito' => $this->bonus_credito,
            'bonus_gestor_comercial' => $this->bonus_gestor_comercial,
            'bonus_equipe_comercial' => $this->bonus_equipe_comercial,
            'pagamento_comissao_desligamento' => $this->pagamento_comissao_desligamento,
            'parcelamento_comissao_meses' => $this->parcelamento_comissao_meses,
            'parcelamento_comissao_terreno' => $this->parcelamento_comissao_terreno,
            'marketing' => $this->marketing,
            'marketing_inicio_antes_lancamento' => $this->marketing_inicio_antes_lancamento,
            'itbi_iptu' => $this->itbi_iptu,
            'registro' => $this->registro,
            'custo_contratacao_cef' => $this->custo_contratacao_cef,
            'custo_medicao_cef' => $this->custo_medicao_cef,
            'contratos_cef' => $this->contratos_cef,
            'produtos_cef' => $this->produtos_cef,
            'outras_despesas_financeiras' => $this->outras_despesas_financeiras,
            'despesas_onerosas_bancos' => $this->despesas_onerosas_bancos,
            'prazo_obra' => $this->prazo_obra,
            'compra_terreno' => $this->compra_terreno,
            'porcentagem_lote_proprietario' => $this->porcentagem_lote_proprietario,
            'taxa_juros_pj' => $this->taxa_juros_pj,
            'carencia_pj_meses' => $this->carencia_pj_meses,
            'amortizacao_pj_parcelas' => $this->amortizacao_pj_parcelas,
            'percentual_antecipacao_pj' => $this->percentual_antecipacao_pj,
            'aporte_adicional_mensal' => $this->aporte_adicional_mensal,
            'devolucao_aporte_percentual' => $this->devolucao_aporte_percentual,
            'distribuicao_lucros_percentual_obra' => $this->distribuicao_lucros_percentual_obra,
            'taxa_exposicao_aplicada' => $this->taxa_exposicao_aplicada,
            'avaliacao_lotes_cef' => $this->avaliacao_lotes_cef,
            'inadimplencia' => $this->inadimplencia,
            'atraso_meses' => $this->atraso_meses,
            'taxa_perda' => $this->taxa_perda,
            'meses_incorporacao' => $this->meses_incorporacao,
            'meses_lancamento' => $this->meses_lancamento,
            'meses_entrega' => $this->meses_entrega,
            'meses_pos_obra' => $this->meses_pos_obra,
            'variavel_correcao' => $this->variavel_correcao,
            'created_at' => $this->created_at?->format('d/m/Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d/m/Y H:i:s'),
        ];
    }
}
