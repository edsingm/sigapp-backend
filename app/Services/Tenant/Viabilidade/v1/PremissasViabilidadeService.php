<?php

namespace App\Services\Tenant\Viabilidade\v1;

use App\Models\Tenant\PremissasViabilidade;
use Carbon\Carbon;
use RuntimeException;

class PremissasViabilidadeService
{
    /**
     * Resolve os valores padrão das premissas exclusivamente do banco de dados.
     *
     * Se não houver registro ativo e vigente em premissas_viabilidade para o
     * perfil solicitado, lança RuntimeException.
     *
     * O config/viabilidade.php é usado apenas como fonte de seed inicial e
     * NÃO é consultado em runtime.
     *
     * @param  string|null  $perfil  Perfil de financiamento ('cef' ou 'proprio')
     * @return array<string, mixed>
     *
     * @throws RuntimeException Se não houver premissa ativa no banco
     */
    public function resolverDefaults(?string $perfil = null): array
    {
        $premissa = PremissasViabilidade::carregarAtiva($perfil);

        if (! $premissa) {
            $perfilLabel = $perfil ?? 'qualquer';
            throw new RuntimeException(
                "Nenhuma premissa de viabilidade ativa encontrada para o perfil '{$perfilLabel}'. ".
                'Execute o PremissasViabilidadeSeeder ou cadastre as premissas manualmente.'
            );
        }

        return [
            'pis_cofins'                       => (float) $premissa->pis_cofins,
            'iss'                              => (float) $premissa->iss,
            'outros_impostos'                  => (float) $premissa->outros_impostos,
            'comissao'                         => (float) $premissa->comissao,
            'parceria_vgv'                     => (float) $premissa->parceria_vgv,
            'infra_nao_incidente'              => (float) $premissa->infra_nao_incidente,
            'incorporacao'                     => (float) $premissa->incorporacao,
            'incorp_ri'                        => (float) $premissa->incorp_ri,
            'incorp_entrega'                   => (float) $premissa->incorp_entrega,
            'incorp_ate_lancamento'            => (float) $premissa->incorp_ate_lancamento,
            'area_comum'                       => (float) $premissa->area_comum,
            'contrapartidas'                   => (float) $premissa->contrapartidas,
            'canteiro_mensal'                  => (float) $premissa->canteiro_mensal,
            'mo_administrativa'                => (float) $premissa->mo_administrativa,
            'seguros'                          => (float) $premissa->seguros,
            'assistencia_tecnica'              => (float) $premissa->assistencia_tecnica,
            'despesas_comerciais'              => (float) $premissa->despesas_comerciais,
            'stand_vendas'                     => (float) $premissa->stand_vendas,
            'mobilia_decoracao'                => (float) $premissa->mobilia_decoracao,
            'gastos_mensais_stand'             => (float) $premissa->gastos_mensais_stand,
            'comissao_house_percentual'        => (float) $premissa->comissao_house_percentual,
            'comissao_imobiliarias_percentual' => (float) $premissa->comissao_imobiliarias_percentual,
            'percentual_vendas_house'          => (float) $premissa->percentual_vendas_house,
            'construcao_stand_meses_antes_lancamento' => (int) $premissa->construcao_stand_meses_antes_lancamento,
            'ajuda_custo_gerente'              => (float) $premissa->ajuda_custo_gerente,
            'ajuda_custo_gerente_regional'     => (float) $premissa->ajuda_custo_gerente_regional,
            'reembolso_logistica'              => (float) $premissa->reembolso_logistica,
            'bonus_cca'                        => (float) $premissa->bonus_cca,
            'bonus_gerente'                    => (float) $premissa->bonus_gerente,
            'bonus_gerente_regional'           => (float) $premissa->bonus_gerente_regional,
            'bonus_credito'                    => (float) $premissa->bonus_credito,
            'bonus_gestor_comercial'           => (float) $premissa->bonus_gestor_comercial,
            'bonus_equipe_comercial'           => (float) $premissa->bonus_equipe_comercial,
            'pagamento_comissao_venda'         => (float) $premissa->pagamento_comissao_venda,
            'pagamento_comissao_desligamento'  => (float) $premissa->pagamento_comissao_desligamento,
            'parcelamento_comissao_meses'      => (int) $premissa->parcelamento_comissao_meses,
            'parcelamento_comissao_terreno'    => (int) $premissa->parcelamento_comissao_terreno,
            'marketing'                        => (float) $premissa->marketing,
            'marketing_lancamento'             => (float) $premissa->marketing_lancamento,
            'marketing_inicio_antes_lancamento' => (int) $premissa->marketing_inicio_antes_lancamento,
            'itbi_iptu'                        => (float) $premissa->itbi_iptu,
            'registro'                         => (float) $premissa->registro,
            'custo_contratacao_cef'            => (float) $premissa->custo_contratacao_cef,
            'custo_medicao_cef'                => (float) $premissa->custo_medicao_cef,
            'contratos_cef'                    => (float) $premissa->contratos_cef,
            'produtos_cef'                     => (float) $premissa->produtos_cef,
            'outras_despesas_financeiras'      => (float) $premissa->outras_despesas_financeiras,
            'despesas_onerosas_bancos'         => (float) $premissa->despesas_onerosas_bancos,
            'prazo_obra'                       => (int) $premissa->prazo_obra,
            'compra_terreno'                   => (float) $premissa->compra_terreno,
            'porcentagem_lote_proprietario'    => (float) $premissa->porcentagem_lote_proprietario,
            'taxa_juros_pj'                    => (float) $premissa->taxa_juros_pj,
            'carencia_pj_meses'                => (int) $premissa->carencia_pj_meses,
            'amortizacao_pj_parcelas'          => (int) $premissa->amortizacao_pj_parcelas,
            'percentual_antecipacao_pj'        => (float) $premissa->percentual_antecipacao_pj,
            'aporte_adicional_mensal'          => (float) $premissa->aporte_adicional_mensal,
            'devolucao_aporte_percentual'      => (float) $premissa->devolucao_aporte_percentual,
            'distribuicao_lucros_percentual_obra' => (float) $premissa->distribuicao_lucros_percentual_obra,
            'taxa_exposicao_aplicada'          => (float) $premissa->taxa_exposicao_aplicada,
            'perfil_financiamento'             => $premissa->perfil_financiamento?->value ?? 'cef',
            'inadimplencia'                    => (float) $premissa->inadimplencia,
            'atraso_meses'                     => (int) $premissa->atraso_meses,
            'taxa_perda'                       => (float) $premissa->taxa_perda,
            'meses_incorporacao'               => (int) $premissa->meses_incorporacao,
            'meses_lancamento'                 => (int) $premissa->meses_lancamento,
            'meses_entrega'                    => (int) $premissa->meses_entrega,
            'meses_pos_obra'                   => (int) $premissa->meses_pos_obra,
            'obra_ate_lancamento'              => (float) $premissa->obra_ate_lancamento,
            'data_lancamento_padrao'           => Carbon::now()->addYears(2),
        ];
    }
}
