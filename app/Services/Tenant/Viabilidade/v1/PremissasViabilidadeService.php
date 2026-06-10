<?php

namespace App\Services\Tenant\Viabilidade\v1;

use App\Enums\PerfilFinanciamento;
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
            'pis_cofins' => $this->floatAttribute($premissa, 'pis_cofins'),
            'iss' => $this->floatAttribute($premissa, 'iss'),
            'outros_impostos' => $this->floatAttribute($premissa, 'outros_impostos'),
            'comissao' => $this->floatAttribute($premissa, 'comissao'),
            'parceria_vgv' => $this->floatAttribute($premissa, 'parceria_vgv'),
            'infra_nao_incidente' => $this->floatAttribute($premissa, 'infra_nao_incidente'),
            'incorporacao' => $this->floatAttribute($premissa, 'incorporacao'),
            'incorp_ri' => $this->floatAttribute($premissa, 'incorp_ri'),
            'incorp_entrega' => $this->floatAttribute($premissa, 'incorp_entrega'),
            'incorp_ate_lancamento' => $this->floatAttribute($premissa, 'incorp_ate_lancamento'),
            'area_comum' => $this->floatAttribute($premissa, 'area_comum'),
            'contrapartidas' => $this->floatAttribute($premissa, 'contrapartidas'),
            'canteiro_mensal' => $this->floatAttribute($premissa, 'canteiro_mensal'),
            'mo_administrativa' => $this->floatAttribute($premissa, 'mo_administrativa'),
            'seguros' => $this->floatAttribute($premissa, 'seguros'),
            'assistencia_tecnica' => $this->floatAttribute($premissa, 'assistencia_tecnica'),
            'despesas_comerciais' => $this->floatAttribute($premissa, 'despesas_comerciais'),
            'stand_vendas' => $this->floatAttribute($premissa, 'stand_vendas'),
            'mobilia_decoracao' => $this->floatAttribute($premissa, 'mobilia_decoracao'),
            'gastos_mensais_stand' => $this->floatAttribute($premissa, 'gastos_mensais_stand'),
            'comissao_house_percentual' => $this->floatAttribute($premissa, 'comissao_house_percentual'),
            'comissao_imobiliarias_percentual' => $this->floatAttribute($premissa, 'comissao_imobiliarias_percentual'),
            'percentual_vendas_house' => $this->floatAttribute($premissa, 'percentual_vendas_house'),
            'construcao_stand_meses_antes_lancamento' => $this->intAttribute($premissa, 'construcao_stand_meses_antes_lancamento'),
            'ajuda_custo_gerente' => $this->floatAttribute($premissa, 'ajuda_custo_gerente'),
            'ajuda_custo_gerente_regional' => $this->floatAttribute($premissa, 'ajuda_custo_gerente_regional'),
            'reembolso_logistica' => $this->floatAttribute($premissa, 'reembolso_logistica'),
            'bonus_cca' => $this->floatAttribute($premissa, 'bonus_cca'),
            'bonus_gerente' => $this->floatAttribute($premissa, 'bonus_gerente'),
            'bonus_gerente_regional' => $this->floatAttribute($premissa, 'bonus_gerente_regional'),
            'bonus_credito' => $this->floatAttribute($premissa, 'bonus_credito'),
            'bonus_gestor_comercial' => $this->floatAttribute($premissa, 'bonus_gestor_comercial'),
            'bonus_equipe_comercial' => $this->floatAttribute($premissa, 'bonus_equipe_comercial'),
            'pagamento_comissao_venda' => $this->floatAttribute($premissa, 'pagamento_comissao_venda'),
            'pagamento_comissao_desligamento' => $this->floatAttribute($premissa, 'pagamento_comissao_desligamento'),
            'parcelamento_comissao_meses' => $this->intAttribute($premissa, 'parcelamento_comissao_meses'),
            'parcelamento_comissao_terreno' => $this->intAttribute($premissa, 'parcelamento_comissao_terreno'),
            'marketing' => $this->floatAttribute($premissa, 'marketing'),
            'marketing_lancamento' => $this->floatAttribute($premissa, 'marketing_lancamento'),
            'marketing_inicio_antes_lancamento' => $this->intAttribute($premissa, 'marketing_inicio_antes_lancamento'),
            'itbi_iptu' => $this->floatAttribute($premissa, 'itbi_iptu'),
            'registro' => $this->floatAttribute($premissa, 'registro'),
            'custo_contratacao_cef' => $this->floatAttribute($premissa, 'custo_contratacao_cef'),
            'custo_medicao_cef' => $this->floatAttribute($premissa, 'custo_medicao_cef'),
            'contratos_cef' => $this->floatAttribute($premissa, 'contratos_cef'),
            'produtos_cef' => $this->floatAttribute($premissa, 'produtos_cef'),
            'outras_despesas_financeiras' => $this->floatAttribute($premissa, 'outras_despesas_financeiras'),
            'despesas_onerosas_bancos' => $this->floatAttribute($premissa, 'despesas_onerosas_bancos'),
            'prazo_obra' => $this->intAttribute($premissa, 'prazo_obra'),
            'compra_terreno' => $this->floatAttribute($premissa, 'compra_terreno'),
            'porcentagem_lote_proprietario' => $this->floatAttribute($premissa, 'porcentagem_lote_proprietario'),
            'taxa_juros_pj' => $this->floatAttribute($premissa, 'taxa_juros_pj'),
            'carencia_pj_meses' => $this->intAttribute($premissa, 'carencia_pj_meses'),
            'amortizacao_pj_parcelas' => $this->intAttribute($premissa, 'amortizacao_pj_parcelas'),
            'percentual_antecipacao_pj' => $this->floatAttribute($premissa, 'percentual_antecipacao_pj'),
            'aporte_adicional_mensal' => $this->floatAttribute($premissa, 'aporte_adicional_mensal'),
            'devolucao_aporte_percentual' => $this->floatAttribute($premissa, 'devolucao_aporte_percentual'),
            'distribuicao_lucros_percentual_obra' => $this->floatAttribute($premissa, 'distribuicao_lucros_percentual_obra'),
            'taxa_exposicao_aplicada' => $this->floatAttribute($premissa, 'taxa_exposicao_aplicada'),
            'perfil_financiamento' => $this->perfilValue($premissa),
            'inadimplencia' => $this->floatAttribute($premissa, 'inadimplencia'),
            'atraso_meses' => $this->intAttribute($premissa, 'atraso_meses'),
            'taxa_perda' => $this->floatAttribute($premissa, 'taxa_perda'),
            'meses_incorporacao' => $this->intAttribute($premissa, 'meses_incorporacao'),
            'meses_lancamento' => $this->intAttribute($premissa, 'meses_lancamento'),
            'meses_entrega' => $this->intAttribute($premissa, 'meses_entrega'),
            'meses_pos_obra' => $this->intAttribute($premissa, 'meses_pos_obra'),
            'obra_ate_lancamento' => $this->floatAttribute($premissa, 'obra_ate_lancamento'),
            'data_lancamento_padrao' => Carbon::now()->addYears(2),
        ];
    }

    private function floatAttribute(PremissasViabilidade $premissa, string $key): float
    {
        return (float) $premissa->getAttribute($key);
    }

    private function intAttribute(PremissasViabilidade $premissa, string $key): int
    {
        return (int) $premissa->getAttribute($key);
    }

    private function perfilValue(PremissasViabilidade $premissa): string
    {
        $perfil = $premissa->getAttribute('perfil_financiamento');

        return $perfil instanceof PerfilFinanciamento ? $perfil->value : 'cef';
    }
}
