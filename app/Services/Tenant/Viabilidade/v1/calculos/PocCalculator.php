<?php

namespace App\Services\Tenant\Viabilidade\v1\Calculos;

class PocCalculator
{
    public function calcularDreContabilPoc(array $fluxo, array $dre, array $dadosProdutos): array
    {
        $custoOrcadoObra = ($dadosProdutos['custoObraHabitacao'] ?? 0) + ($dadosProdutos['custoInfraestrutura'] ?? 0);
        $custoIncorridoObra = 0.0;
        $custoIncorridoTotal = 0.0;
        foreach ($fluxo as $linha) {
            $despesas = $linha['despesas'] ?? [];
            $custoIncorridoObra += (float) ($despesas['obra']['obra_periodo_obra'] ?? 0);
            $custoIncorridoTotal += (float) ($linha['despesas']['total'] ?? 0);
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

    public function calcularQuadroPocMensal(array $fluxo, array $dre, array $dadosProdutos): array
    {
        $quadro = [];
        $custoOrcadoObra = max(0.0, (float) (($dadosProdutos['custoObraHabitacao'] ?? 0) + ($dadosProdutos['custoInfraestrutura'] ?? 0)));
        $receitaTotalVendas = max(0.0, (float) ($dre['receita_total_vendas'] ?? 0));
        $custoObraAcumulado = 0.0;
        $receitaReconhecidaAcumulada = 0.0;

        foreach ($fluxo as $mes => $linha) {
            $despesas = $linha['despesas'] ?? [];
            $custoObraMes = max(0.0, (float) ($despesas['obra']['obra_periodo_obra'] ?? 0));
            $custoObraAcumulado += $custoObraMes;
            $execucaoAcumulada = $custoOrcadoObra > 0 ? min(1, $custoObraAcumulado / $custoOrcadoObra) : 0.0;
            $receitaReconhecidaAlvo = $receitaTotalVendas * $execucaoAcumulada;
            $receitaReconhecidaMes = max(0.0, $receitaReconhecidaAlvo - $receitaReconhecidaAcumulada);
            $receitaReconhecidaAcumulada += $receitaReconhecidaMes;
            $custoTotalMes = (float) ($linha['despesas']['total'] ?? 0);
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
            $custoObraMes = max(0.0, (float) ($despesas['obra']['obra_periodo_obra'] ?? 0));
            $custoObraAcumulado += $custoObraMes;
            $execucaoAcumulada = $custoOrcadoObra > 0 ? min(1, $custoObraAcumulado / $custoOrcadoObra) : 0.0;
            $receitaPocAlvo = $receitaTotalVendas * $execucaoAcumulada;
            $receitaPocMes = max(0.0, $receitaPocAlvo - $receitaPocAcumulada);
            $receitaPocAcumulada += $receitaPocMes;

            $impostosMes = max(0.0, (float) ($despesas['deducoes']['total_impostos'] ?? 0));
            $operacionalMes = max(0.0,
                (float) ($despesas['despesas_comerciais']['total_despesas_comerciais'] ?? 0)
                + (float) ($despesas['marketing']['total_marketing'] ?? 0)
                + (float) ($despesas['itbi_registro']['total_itbi_registro'] ?? 0)
                + (float) ($despesas['taxa_caixa']['total_caixa'] ?? 0)
            );
            $financeiroMes = max(0.0, (float) ($despesas['outras_despesas_financeiras'] ?? 0));
            $custosTotaisMes = max(0.0, (float) ($linha['despesas']['total'] ?? 0));
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
}
