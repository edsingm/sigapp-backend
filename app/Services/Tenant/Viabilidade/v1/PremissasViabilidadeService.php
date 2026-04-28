<?php

namespace App\Services\Tenant\Viabilidade\v1;

use App\Enums\PerfilFinanciamento;
use App\Models\Tenant\PremissasViabilidade;
use Carbon\Carbon;

class PremissasViabilidadeService
{
    /**
     * Resolve os valores padrão das premissas, nesta ordem:
     * 1. Registro ativo e vigente em premissas_viabilidade (banco), filtrado por perfil
     * 2. config('viabilidade.defaults') + config('viabilidade.prazos')
     *
     * Retorna um array com os mesmos nomes que o antigo montarParametros()
     * consumia de $d e $p, para que a refatoração seja drop-in.
     *
     * @param string|null $perfil Perfil de financiamento para filtrar premissas ('cef' ou 'proprio')
     */
    public function resolverDefaults(?string $perfil = null): array
    {
        $premissa = PremissasViabilidade::carregarAtiva($perfil);
        $d = config('viabilidade.defaults', []);
        $p = config('viabilidade.prazos', []);

        return [
            'pis_cofins'                 => $this->float($premissa?->pis_cofins, $d['pis_cofins'] ?? 4.0),
            'iss'                        => $this->float($premissa?->iss, $d['iss'] ?? 0.0),
            'outros_impostos'            => $this->float($premissa?->outros_impostos, $d['outros_impostos'] ?? 0.5),
            'comissao'                   => $this->float($premissa?->comissao, $d['comissao'] ?? 0.0),
            'parceria_vgv'               => $this->float($premissa?->parceria_vgv, $d['parceria_vgv'] ?? 0.0),
            'infra_nao_incidente'        => $this->float($premissa?->infra_nao_incidente, $d['infra_nao_incidente'] ?? 1.0),
            'incorporacao'               => $this->float($premissa?->incorporacao, $d['incorporacao'] ?? 1.0),
            'area_comum'                 => $this->float($premissa?->area_comum, $d['area_comum'] ?? 0.0),
            'contrapartidas'             => $this->float($premissa?->contrapartidas, $d['contrapartidas'] ?? 0.0),
            'canteiro_mensal'            => $this->float($premissa?->canteiro_mensal, $d['canteiro_mensal'] ?? 85715.0),
            'mo_administrativa'          => $this->float($premissa?->mo_administrativa, $d['mo_administrativa'] ?? 62502.0),
            'seguros'                    => $this->float($premissa?->seguros, $d['seguros'] ?? 0.5),
            'assistencia_tecnica'        => $this->float($premissa?->assistencia_tecnica, $d['assistencia_tecnica'] ?? 1.0),
            'despesas_comerciais'        => $this->float($premissa?->despesas_comerciais, $d['despesas_comerciais'] ?? 5.0),
            'stand_vendas'               => $this->float($premissa?->stand_vendas, $d['stand_vendas'] ?? 0.0),
            'mobilia_decoracao'          => $this->float($premissa?->mobilia_decoracao, $d['mobilia_decoracao'] ?? 90000.0),
            'ajuda_custo_gerente'        => $this->float($premissa?->ajuda_custo_gerente, $d['ajuda_custo_gerente'] ?? 5000.0),
            'ajuda_custo_gerente_regional' => $this->float($premissa?->ajuda_custo_gerente_regional, $d['ajuda_custo_gerente_regional'] ?? 2733.0),
            'reembolso_logistica'        => $this->float($premissa?->reembolso_logistica, $d['reembolso_logistica'] ?? 5000.0),
            'bonus_cca'                  => $this->float($premissa?->bonus_cca, $d['bonus_cca'] ?? 350.0),
            'bonus_gerente'              => $this->float($premissa?->bonus_gerente, $d['bonus_gerente'] ?? 0.3),
            'bonus_gerente_regional'     => $this->float($premissa?->bonus_gerente_regional, $d['bonus_gerente_regional'] ?? 0.12),
            'bonus_credito'              => $this->float($premissa?->bonus_credito, $d['bonus_credito'] ?? 0.05),
            'bonus_gestor_comercial'     => $this->float($premissa?->bonus_gestor_comercial, $d['bonus_gestor_comercial'] ?? 0.05),
            'pagamento_comissao_desligamento' => $this->float($premissa?->pagamento_comissao_desligamento, $d['pagamento_comissao_desligamento'] ?? 50.0),
            'parcelamento_comissao_meses' => $this->int($premissa?->parcelamento_comissao_meses, $d['parcelamento_comissao_meses'] ?? 18),
            'marketing'                  => $this->float($premissa?->marketing, $d['marketing'] ?? 1.0),
            'itbi_iptu'                  => $this->float($premissa?->itbi_iptu, $d['itbi_iptu'] ?? 1.1),
            'registro'                   => $this->float($premissa?->registro, $d['registro'] ?? 2500.0),
            'custo_contratacao_cef'      => $this->float($premissa?->custo_contratacao_cef, $d['custo_contratacao_cef'] ?? 0.0),
            'custo_medicao_cef'          => $this->float($premissa?->custo_medicao_cef, $d['custo_medicao_cef'] ?? 0.0),
            'contratos_cef'              => $this->float($premissa?->contratos_cef, $d['contratos_cef'] ?? 300.0),
            'produtos_cef'               => $this->float($premissa?->produtos_cef, $d['produtos_cef'] ?? 0.5),
            'outras_despesas_financeiras' => $this->float($premissa?->outras_despesas_financeiras, $d['outras_despesas_financeiras'] ?? 0.3),
            'despesas_onerosas_bancos'   => $this->float($premissa?->despesas_onerosas_bancos, $d['despesas_onerosas_bancos'] ?? 10.0),
            'prazo_obra'                 => $this->int($premissa?->prazo_obra, $d['prazo_obra'] ?? 36),
            'compra_terreno'              => $this->float($premissa?->compra_terreno, 0.0),
            'porcentagem_lote_proprietario' => $this->float($premissa?->porcentagem_lote_proprietario, $d['porcentagem_lote_proprietario'] ?? 10.0),
            'taxa_juros_pj'              => $this->float($premissa?->taxa_juros_pj, $d['taxa_juros_pj'] ?? 10.5),
            'percentual_antecipacao_pj'  => $this->float($premissa?->percentual_antecipacao_pj, $d['percentual_antecipacao_pj'] ?? 10.0),
            'aporte_adicional_mensal'    => $this->float($premissa?->aporte_adicional_mensal, $d['aporte_adicional_mensal'] ?? 0.0),
            'devolucao_aporte_percentual' => $this->float($premissa?->devolucao_aporte_percentual, $d['devolucao_aporte_percentual'] ?? 20.0),
            'distribuicao_lucros_percentual_obra' => $this->float($premissa?->distribuicao_lucros_percentual_obra, $d['distribuicao_lucros_percentual_obra'] ?? 100.0),
            'taxa_exposicao_aplicada'    => $this->float($premissa?->taxa_exposicao_aplicada, $d['taxa_exposicao_aplicada'] ?? 12.5),
            'avaliacao_lotes_cef'        => $premissa?->avaliacao_lotes_cef ?? $d['avaliacao_lotes_cef'] ?? [],
            'perfil_financiamento'       => $this->perfilStr($premissa?->perfil_financiamento, $d['perfil_financiamento'] ?? 'cef'),
            'inadimplencia'              => $this->float($premissa?->inadimplencia, $d['inadimplencia'] ?? 0.10),
            'atraso_meses'               => $this->int($premissa?->atraso_meses, $d['atraso_meses'] ?? 2),
            'taxa_perda'                 => $this->float($premissa?->taxa_perda, $d['taxa_perda'] ?? 0.02),
            'meses_incorporacao'         => $this->int($premissa?->meses_incorporacao, $p['meses_incorporacao'] ?? 18),
            'meses_lancamento'           => $this->int($premissa?->meses_lancamento, $p['meses_lancamento'] ?? 6),
            'meses_entrega'              => $this->int($premissa?->meses_entrega, $p['meses_entrega'] ?? 1),
            'meses_pos_obra'             => $this->int($premissa?->meses_pos_obra, $p['meses_pos_obra'] ?? 60),
            'variavel_correcao'          => $this->float($premissa?->variavel_correcao, $p['variavel_correcao'] ?? 0.027545),
            'data_lancamento_padrao'     => $this->dataLancamentoPadrao(),
        ];
    }

    private function float(mixed $dbValue, mixed $fallback): float
    {
        $value = $dbValue ?? $fallback;

        return (float) $value;
    }

    private function int(mixed $dbValue, mixed $fallback): int
    {
        $value = $dbValue ?? $fallback;

        return (int) $value;
    }

    private function dataLancamentoPadrao(): Carbon
    {
        return Carbon::now()->addYears(2);
    }

    private function perfilStr(mixed $dbValue, mixed $fallback): string
    {
        if ($dbValue instanceof PerfilFinanciamento) {
            return $dbValue->value;
        }

        return (string) ($dbValue ?? $fallback);
    }
}
