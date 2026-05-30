<?php

namespace App\Services\Tenant\Viabilidade\v1\Calculos;

class PocCalculator
{
    /**
     * @param  array<string, array<string, mixed>>  $fluxo
     * @param  array<string, mixed>  $dre
     * @param  array<string, mixed>  $dadosProdutos
     * @return array<string, float>
     */
    public function calcularDreContabilPoc(array $fluxo, array $dre, array $dadosProdutos): array
    {
        $custoOrcadoObra = (float) ($dadosProdutos['custoObraHabitacao'] ?? 0) + (float) ($dadosProdutos['custoInfraestrutura'] ?? 0);
        $custoIncorridoObra = 0.0;
        $custoIncorridoTotal = 0.0;
        foreach ($fluxo as $linha) {
            $despesas = $linha['despesas'] ?? [];
            $custoIncorridoObra += $this->extractObraMes($despesas);
            $custoIncorridoTotal += $this->extractTotalDespesasMes($linha);
        }

        $percentualExecucaoObra = $custoOrcadoObra > 0 ? min(1, $custoIncorridoObra / $custoOrcadoObra) : 0;
        $receitaTotalVendas = (float) ($dre['receita_total_vendas'] ?? 0);
        $receitaReconhecida = $receitaTotalVendas * $percentualExecucaoObra;
        $lucroBrutoContabil = $receitaReconhecida - $custoIncorridoTotal;
        $margemBrutaContabil = $receitaReconhecida > 0 ? ($lucroBrutoContabil / $receitaReconhecida) : 0;

        return [
            'custo_orcado_obra' => round($custoOrcadoObra, 2),
            'custo_incorrido_obra' => round($custoIncorridoObra, 2),
            'percentual_execucao_obra' => round($percentualExecucaoObra * 100, 2),
            'receita_reconhecida_poc' => round($receitaReconhecida, 2),
            'custo_incorrido_total' => round($custoIncorridoTotal, 2),
            'lucro_bruto_contabil' => round($lucroBrutoContabil, 2),
            'margem_bruta_contabil_percentual' => round($margemBrutaContabil * 100, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $totais
     * @return array<string, float>
     */
    public function calcularDreCaixa(array $totais): array
    {
        $receitaTotal = (float) ($totais['receita'] ?? 0.0);
        $custoDireto = (float) ($totais['custo_direto'] ?? 0.0);
        $impostos = (float) ($totais['impostos'] ?? 0.0);
        $operacionais = (float) ($totais['custos_operacionais'] ?? 0.0);
        $financeiros = (float) ($totais['custos_financeiros'] ?? 0.0);
        $despesasTotal = $custoDireto + $impostos + $operacionais + $financeiros;
        $resultado = (float) ($totais['lucro'] ?? ($receitaTotal - $despesasTotal));
        $margem = $receitaTotal > 0 ? ($resultado / $receitaTotal) : 0.0;

        return [
            'receita_total' => round($receitaTotal, 2),
            'custo_direto_total' => round($custoDireto, 2),
            'impostos_total' => round($impostos, 2),
            'despesas_operacionais_total' => round($operacionais, 2),
            'despesas_financeiras_total' => round($financeiros, 2),
            'despesas_total' => round($despesasTotal, 2),
            'resultado_total' => round($resultado, 2),
            'margem_liquida_percentual' => round($margem * 100, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $dreCaixa
     * @param  array<string, mixed>  $dreGerencial
     * @param  array<string, mixed>  $drePocMensalBlocos
     * @return array<string, array<int|string, float|string>>
     */
    public function calcularPonteReconcilicao(array $dreCaixa, array $dreGerencial, array $drePocMensalBlocos): array
    {
        $caixaReceita = (float) ($dreCaixa['receita_total'] ?? 0.0);
        $caixaResultado = (float) ($dreCaixa['resultado_total'] ?? 0.0);

        $dreReceitaBruta = (float) ($dreGerencial['receita_bruta'] ?? 0.0);
        $dreResultado = (float) ($dreGerencial['lucro_liquido_projeto'] ?? 0.0);
        $dreIr = (float) ($dreGerencial['irpj_csll'] ?? 0.0);
        $dreJurosPj = (float) ($dreGerencial['despesas_onerosas_bancos'] ?? 0.0);

        $pocResumo = is_array($drePocMensalBlocos['resumo'] ?? null) ? $drePocMensalBlocos['resumo'] : [];
        $pocReceita = (float) ($pocResumo['receita_reconhecida_poc_total'] ?? 0.0);
        $pocResultado = (float) ($pocResumo['resultado_contabil_total'] ?? 0.0);

        return [
            'caixa' => [
                'receita_total' => round($caixaReceita, 2),
                'resultado_total' => round($caixaResultado, 2),
            ],
            'dre_gerencial' => [
                'receita_bruta' => round($dreReceitaBruta, 2),
                'lucro_liquido' => round($dreResultado, 2),
                'irpj_csll' => round($dreIr, 2),
                'juros_pj' => round($dreJurosPj, 2),
            ],
            'dre_contabil_poc' => [
                'receita_reconhecida_poc_total' => round($pocReceita, 2),
                'resultado_contabil_total' => round($pocResultado, 2),
            ],
            'deltas' => [
                'receita_caixa_menos_receita_bruta' => round($caixaReceita - $dreReceitaBruta, 2),
                'resultado_caixa_menos_lucro_dre_gerencial' => round($caixaResultado - $dreResultado, 2),
                'lucro_dre_gerencial_menos_resultado_poc' => round($dreResultado - $pocResultado, 2),
            ],
            'principais_motivos' => [
                'Caixa considera entradas/saidas; DRE gerencial usa VGV e premissas de correcao/juros para formar receita e apropria custos por regra.',
                'DRE contabil (POC) reconhece receita e custos conforme execucao da obra, nao conforme recebimento.',
                'IRPJ/CSLL e despesas onerosas (juros PJ) tendem a aparecer na DRE gerencial/financeira e nao sao necessariamente desembolso no mesmo timing do caixa operacional.',
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $fluxo
     * @param  array<string, mixed>  $dre
     * @param  array<string, mixed>  $dadosProdutos
     * @return array{meses: array<string, array<string, float>>, resumo: array<string, float>}
     */
    public function calcularQuadroPocMensal(array $fluxo, array $dre, array $dadosProdutos): array
    {
        $quadro = [];
        $custoOrcadoObra = max(0.0, (float) (($dadosProdutos['custoObraHabitacao'] ?? 0) + ($dadosProdutos['custoInfraestrutura'] ?? 0)));
        $receitaTotalVendas = max(0.0, (float) ($dre['receita_total_vendas'] ?? 0));
        $custoObraAcumulado = 0.0;
        $receitaReconhecidaAcumulada = 0.0;

        foreach ($fluxo as $mes => $linha) {
            $despesas = $linha['despesas'] ?? [];
            $custoObraMes = max(0.0, $this->extractObraMes($despesas));
            $custoObraAcumulado += $custoObraMes;
            $execucaoAcumulada = $custoOrcadoObra > 0 ? min(1, $custoObraAcumulado / $custoOrcadoObra) : 0.0;
            $receitaReconhecidaAlvo = $receitaTotalVendas * $execucaoAcumulada;
            $receitaReconhecidaMes = max(0.0, $receitaReconhecidaAlvo - $receitaReconhecidaAcumulada);
            $receitaReconhecidaAcumulada += $receitaReconhecidaMes;
            $custoTotalMes = $this->extractTotalDespesasMes($linha);
            $resultadoContabilMes = $receitaReconhecidaMes - $custoTotalMes;

            $quadro[$mes] = [
                'percentual_execucao_obra_acumulado' => round($execucaoAcumulada * 100, 2),
                'custo_obra_mes' => round($custoObraMes, 2),
                'custo_obra_acumulado' => round($custoObraAcumulado, 2),
                'receita_reconhecida_poc_mes' => round($receitaReconhecidaMes, 2),
                'receita_reconhecida_poc_acumulada' => round($receitaReconhecidaAcumulada, 2),
                'resultado_contabil_mes' => round($resultadoContabilMes, 2),
            ];
        }

        return [
            'meses' => $quadro,
            'resumo' => [
                'custo_orcado_obra' => round($custoOrcadoObra, 2),
                'custo_obra_acumulado' => round($custoObraAcumulado, 2),
                'receita_reconhecida_poc_total' => round($receitaReconhecidaAcumulada, 2),
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $fluxo
     * @param  array<string, mixed>  $dre
     * @param  array<string, mixed>  $dadosProdutos
     * @return array{meses: array<string, array<string, float>>, resumo: array<string, float>}
     */
    public function calcularQuadroPocMensalPorBlocos(array $fluxo, array $dre, array $dadosProdutos): array
    {
        $quadro = [];
        $custoOrcadoObra = max(0.0, (float) (($dadosProdutos['custoObraHabitacao'] ?? 0) + ($dadosProdutos['custoInfraestrutura'] ?? 0)));
        $receitaTotalVendas = max(0.0, (float) ($dre['receita_total_vendas'] ?? 0));
        $custoObraAcumulado = 0.0;
        $receitaPocAcumulada = 0.0;
        $acumulados = [
            'receita_poc' => 0.0,
            'custo_direto' => 0.0,
            'impostos' => 0.0,
            'operacional' => 0.0,
            'financeiro' => 0.0,
            'resultado' => 0.0,
        ];

        foreach ($fluxo as $mes => $linha) {
            $despesas = $linha['despesas'] ?? [];
            $custoObraMes = max(0.0, $this->extractObraMes($despesas));
            $custoObraAcumulado += $custoObraMes;
            $execucaoAcumulada = $custoOrcadoObra > 0 ? min(1, $custoObraAcumulado / $custoOrcadoObra) : 0.0;
            $receitaPocAlvo = $receitaTotalVendas * $execucaoAcumulada;
            $receitaPocMes = max(0.0, $receitaPocAlvo - $receitaPocAcumulada);
            $receitaPocAcumulada += $receitaPocMes;

            $impostosMes = max(0.0, $this->extractImpostosMes($despesas));
            $operacionalMes = max(0.0, $this->extractOperacionalMes($despesas));
            $financeiroMes = max(0.0, $this->extractFinanceiroMes($despesas));

            $custosTotaisMes = max(0.0, $this->extractTotalDespesasMes($linha));
            $custoDiretoMes = max(0.0, $custosTotaisMes - $impostosMes - $operacionalMes - $financeiroMes);
            $resultadoContabilMes = $receitaPocMes - ($custoDiretoMes + $impostosMes + $operacionalMes + $financeiroMes);

            $acumulados['receita_poc'] += $receitaPocMes;
            $acumulados['custo_direto'] += $custoDiretoMes;
            $acumulados['impostos'] += $impostosMes;
            $acumulados['operacional'] += $operacionalMes;
            $acumulados['financeiro'] += $financeiroMes;
            $acumulados['resultado'] += $resultadoContabilMes;

            $quadro[$mes] = [
                'receita_reconhecida_poc_mes' => round($receitaPocMes, 2),
                'receita_reconhecida_poc_acumulada' => round($acumulados['receita_poc'], 2),
                'custo_direto_mes' => round($custoDiretoMes, 2),
                'custo_direto_acumulado' => round($acumulados['custo_direto'], 2),
                'impostos_mes' => round($impostosMes, 2),
                'impostos_acumulado' => round($acumulados['impostos'], 2),
                'despesas_operacionais_mes' => round($operacionalMes, 2),
                'despesas_operacionais_acumulado' => round($acumulados['operacional'], 2),
                'despesas_financeiras_mes' => round($financeiroMes, 2),
                'despesas_financeiras_acumulado' => round($acumulados['financeiro'], 2),
                'resultado_contabil_mes' => round($resultadoContabilMes, 2),
                'resultado_contabil_acumulado' => round($acumulados['resultado'], 2),
                'receita_caixa_mes' => round((float) ($linha['receitas']['total'] ?? 0), 2),
                'percentual_execucao_obra_acumulado' => round($execucaoAcumulada * 100, 2),
            ];
        }

        $margemContabil = $acumulados['receita_poc'] > 0 ? ($acumulados['resultado'] / $acumulados['receita_poc']) : 0;

        return [
            'meses' => $quadro,
            'resumo' => [
                'receita_reconhecida_poc_total' => round($acumulados['receita_poc'], 2),
                'custo_direto_total' => round($acumulados['custo_direto'], 2),
                'impostos_total' => round($acumulados['impostos'], 2),
                'despesas_operacionais_total' => round($acumulados['operacional'], 2),
                'despesas_financeiras_total' => round($acumulados['financeiro'], 2),
                'resultado_contabil_total' => round($acumulados['resultado'], 2),
                'margem_contabil_percentual' => round($margemContabil * 100, 2),
            ],
        ];
    }

    private function extractObraMes(array $despesas): float
    {
        if (isset($despesas['obra'])) {
            if (is_array($despesas['obra'])) {
                return (float) ($despesas['obra']['obra_periodo_obra'] ?? 0.0);
            }

            return (float) $despesas['obra'];
        }

        if (isset($despesas['Obra'])) {
            return (float) $despesas['Obra'];
        }

        return 0.0;
    }

    private function extractImpostosMes(array $despesas): float
    {
        if (isset($despesas['deducoes'])) {
            if (is_array($despesas['deducoes'])) {
                return (float) ($despesas['deducoes']['total_impostos'] ?? 0.0);
            }

            return (float) $despesas['deducoes'];
        }

        if (isset($despesas['Deduções'])) {
            return (float) $despesas['Deduções'];
        }

        return 0.0;
    }

    private function extractOperacionalMes(array $despesas): float
    {
        if (isset($despesas['operacional'])) {
            return (float) $despesas['operacional'];
        }

        if (isset($despesas['Operacional'])) {
            return (float) $despesas['Operacional'];
        }

        return
            (float) ($despesas['despesas_comerciais']['total_despesas_comerciais'] ?? 0.0)
            + (float) ($despesas['marketing']['total_marketing'] ?? 0.0)
            + (float) ($despesas['itbi_registro']['total_itbi_registro'] ?? 0.0)
            + (float) ($despesas['taxa_caixa']['total_caixa'] ?? 0.0);
    }

    private function extractFinanceiroMes(array $despesas): float
    {
        if (isset($despesas['outras_despesas_financeiras'])) {
            return (float) $despesas['outras_despesas_financeiras'];
        }

        if (isset($despesas['Outras Despesas Financeiras'])) {
            return (float) $despesas['Outras Despesas Financeiras'];
        }

        return 0.0;
    }

    private function extractTotalDespesasMes(array $linha): float
    {
        $despesas = $linha['despesas'] ?? [];
        if (is_array($despesas) && isset($despesas['total'])) {
            return (float) $despesas['total'];
        }

        $receitaTotal = (float) (($linha['receitas']['total'] ?? 0.0));
        $saldoMes = (float) ($linha['saldo_mes'] ?? 0.0);
        $despesaDerivada = $receitaTotal - $saldoMes;

        return max(0.0, $despesaDerivada);
    }
}
