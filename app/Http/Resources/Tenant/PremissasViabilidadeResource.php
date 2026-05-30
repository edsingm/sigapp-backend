<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\PremissasViabilidade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PremissasViabilidade */
class PremissasViabilidadeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $perfilFinanciamento = $this->attr('perfil_financiamento');

        return [
            'id' => $this->attr('id'),
            'nome' => $this->attr('nome'),
            'perfil_financiamento' => $perfilFinanciamento instanceof \App\Enums\PerfilFinanciamento
                ? $perfilFinanciamento->value
                : $perfilFinanciamento,
            'ativo' => $this->attr('ativo'),
            'versao' => $this->attr('versao'),
            'vigente_em' => $this->formattedDate('vigente_em'),
            'encerrada_em' => $this->formattedDate('encerrada_em'),
            'pis_cofins' => $this->attr('pis_cofins'),
            'iss' => $this->attr('iss'),
            'outros_impostos' => $this->attr('outros_impostos'),
            'comissao' => $this->attr('comissao'),
            'parceria_vgv' => $this->attr('parceria_vgv'),
            'infra_nao_incidente' => $this->attr('infra_nao_incidente'),
            'incorporacao' => $this->attr('incorporacao'),
            'incorp_ri' => $this->attr('incorp_ri'),
            'incorp_entrega' => $this->attr('incorp_entrega'),
            'incorp_ate_lancamento' => $this->attr('incorp_ate_lancamento'),
            'obra_ate_lancamento' => $this->attr('obra_ate_lancamento'),
            'area_comum' => $this->attr('area_comum'),
            'contrapartidas' => $this->attr('contrapartidas'),
            'canteiro_mensal' => $this->attr('canteiro_mensal'),
            'mo_administrativa' => $this->attr('mo_administrativa'),
            'seguros' => $this->attr('seguros'),
            'assistencia_tecnica' => $this->attr('assistencia_tecnica'),
            'despesas_comerciais' => $this->attr('despesas_comerciais'),
            'stand_vendas' => $this->attr('stand_vendas'),
            'mobilia_decoracao' => $this->attr('mobilia_decoracao'),
            'gastos_mensais_stand' => $this->attr('gastos_mensais_stand'),
            'comissao_house_percentual' => $this->attr('comissao_house_percentual'),
            'comissao_imobiliarias_percentual' => $this->attr('comissao_imobiliarias_percentual'),
            'percentual_vendas_house' => $this->attr('percentual_vendas_house'),
            'construcao_stand_meses_antes_lancamento' => $this->attr('construcao_stand_meses_antes_lancamento'),
            'ajuda_custo_gerente' => $this->attr('ajuda_custo_gerente'),
            'ajuda_custo_gerente_regional' => $this->attr('ajuda_custo_gerente_regional'),
            'reembolso_logistica' => $this->attr('reembolso_logistica'),
            'bonus_cca' => $this->attr('bonus_cca'),
            'bonus_gerente' => $this->attr('bonus_gerente'),
            'bonus_gerente_regional' => $this->attr('bonus_gerente_regional'),
            'bonus_credito' => $this->attr('bonus_credito'),
            'bonus_gestor_comercial' => $this->attr('bonus_gestor_comercial'),
            'bonus_equipe_comercial' => $this->attr('bonus_equipe_comercial'),
            'pagamento_comissao_venda' => $this->attr('pagamento_comissao_venda'),
            'pagamento_comissao_desligamento' => $this->attr('pagamento_comissao_desligamento'),
            'parcelamento_comissao_meses' => $this->attr('parcelamento_comissao_meses'),
            'parcelamento_comissao_terreno' => $this->attr('parcelamento_comissao_terreno'),
            'marketing' => $this->attr('marketing'),
            'marketing_lancamento' => $this->attr('marketing_lancamento'),
            'marketing_inicio_antes_lancamento' => $this->attr('marketing_inicio_antes_lancamento'),
            'itbi_iptu' => $this->attr('itbi_iptu'),
            'registro' => $this->attr('registro'),
            'custo_contratacao_cef' => $this->attr('custo_contratacao_cef'),
            'custo_medicao_cef' => $this->attr('custo_medicao_cef'),
            'contratos_cef' => $this->attr('contratos_cef'),
            'produtos_cef' => $this->attr('produtos_cef'),
            'outras_despesas_financeiras' => $this->attr('outras_despesas_financeiras'),
            'despesas_onerosas_bancos' => $this->attr('despesas_onerosas_bancos'),
            'prazo_obra' => $this->attr('prazo_obra'),
            'compra_terreno' => $this->attr('compra_terreno'),
            'porcentagem_lote_proprietario' => $this->attr('porcentagem_lote_proprietario'),
            'taxa_juros_pj' => $this->attr('taxa_juros_pj'),
            'carencia_pj_meses' => $this->attr('carencia_pj_meses'),
            'amortizacao_pj_parcelas' => $this->attr('amortizacao_pj_parcelas'),
            'percentual_antecipacao_pj' => $this->attr('percentual_antecipacao_pj'),
            'aporte_adicional_mensal' => $this->attr('aporte_adicional_mensal'),
            'devolucao_aporte_percentual' => $this->attr('devolucao_aporte_percentual'),
            'distribuicao_lucros_percentual_obra' => $this->attr('distribuicao_lucros_percentual_obra'),
            'taxa_exposicao_aplicada' => $this->attr('taxa_exposicao_aplicada'),
            'inadimplencia' => $this->attr('inadimplencia'),
            'atraso_meses' => $this->attr('atraso_meses'),
            'taxa_perda' => $this->attr('taxa_perda'),
            'meses_incorporacao' => $this->attr('meses_incorporacao'),
            'meses_lancamento' => $this->attr('meses_lancamento'),
            'meses_entrega' => $this->attr('meses_entrega'),
            'meses_pos_obra' => $this->attr('meses_pos_obra'),
            'variavel_correcao' => $this->attr('variavel_correcao'),
            'created_at' => $this->formattedDateTime('created_at'),
            'updated_at' => $this->formattedDateTime('updated_at'),
        ];
    }

    private function attr(string $key): mixed
    {
        return $this->resource->getAttribute($key);
    }

    private function formattedDate(string $key): ?string
    {
        $value = $this->attr($key);

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : null;
    }

    private function formattedDateTime(string $key): ?string
    {
        $value = $this->attr($key);

        return $value instanceof \DateTimeInterface ? $value->format('d/m/Y H:i:s') : null;
    }
}
