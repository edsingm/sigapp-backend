<?php

namespace App\Services\Tenant\Viabilidade;

/**
 * ImpostosService - Centraliza todos os cálculos de impostos e tributos
 *
 * Responsabilidades:
 * - Cálculo de PIS, COFINS, ISS
 * - Cálculo de IRPJ, CSLL
 * - Cálculo de tributos sobre receitas
 * - Proporção de impostos por produto
 */
class ImpostosService
{
    /**
     * Calcula tributos mensais sobre uma receita
     *
     * @param  float  $receita  Receita do mês
     * @param  float  $jurosCorrecao  Juros e correção do mês (base diferente para alguns impostos)
     * @param  array  $params  Parâmetros da viabilidade
     */
    public function calcularTributosMensais(float $receita, float $jurosCorrecao, array $params): array
    {
        $percentualImpostos = $params['percentualImpostos'] ?? 0;

        // Base para tributos gerais
        $tributos = $receita * $percentualImpostos;

        return [
            'tributos' => round($tributos, 2),
            'total' => round($tributos, 2),
        ];
    }

    /**
     * Calcula tributos mensais proporcional por produto
     *
     * @param  float  $receitaMes  Receita total do mês
     * @param  float  $jurosCorrecaoMes  Juros e correção do mês
     * @param  array  $produtos  Lista de produtos com seus dados
     * @param  float  $vgvTotal  VGV total do projeto
     * @param  array  $params  Parâmetros da viabilidade
     * @return float Total de tributos do mês
     */
    public function calcularTributosPorProduto(
        float $receitaMes,
        float $jurosCorrecaoMes,
        array $produtos,
        float $vgvTotal,
        array $params
    ): float {
        $tributosMes = 0;

        foreach ($produtos as $p) {
            // Proporção da receita do mês para este produto
            $proporcao = $vgvTotal > 0 ? $p['vgv_produto'] / $vgvTotal : 0;
            $receitaProdutoMes = $receitaMes * $proporcao;
            $jurosCorrecaoProdMes = $jurosCorrecaoMes * $proporcao;

            $impostoTributos = $p['imposto_tributos'] ?? $params['percentualImpostos'];
            $impostoOutros = $p['imposto_outros'] ?? 0;

            $tributosMes += ($receitaProdutoMes * $impostoTributos) +
                (($receitaProdutoMes - $jurosCorrecaoProdMes) * $impostoOutros);
        }

        return round($tributosMes, 2);
    }

    /**
     * Calcula impostos para a DRE completa (visão consolidada)
     *
     * @param  array  $produtos  Dados dos produtos processados
     * @param  float  $vgvSemTerrenista  VGV sem valor do terrenista
     * @return array Impostos detalhados
     */
    public function calcularImpostosDre(array $produtos, float $vgvSemTerrenista): array
    {
        $pis = 0;
        $cofins = 0;
        $iss = 0;
        $irpj = 0;
        $csll = 0;
        $outrasDeducoes = 0;

        foreach ($produtos as $produto) {
            // Impostos já calculados no processamento de produtos
            if (isset($produto['financeiro'])) {
                $pis += $produto['financeiro']['imposto_pis'] ?? 0;
                $cofins += $produto['financeiro']['imposto_cofins'] ?? 0;
                $irpj += $produto['financeiro']['irrpj'] ?? 0;
                $csll += $produto['financeiro']['csll'] ?? 0;
            }
        }

        return [
            'pis' => round($pis, 2),
            'cofins' => round($cofins, 2),
            'iss' => round($iss, 2),
            'irpj' => round($irpj, 2),
            'csll' => round($csll, 2),
            'outras_deducoes' => round($outrasDeducoes, 2),
            'total' => round($pis + $cofins + $iss + $outrasDeducoes, 2),
            'total_ir_csll' => round($irpj + $csll, 2),
        ];
    }

    /**
     * Calcula impostos sobre VGV de um produto individual
     *
     * @param  float  $vgvSemTerrenista  VGV sem valor do terrenista
     * @param  float  $percentualTributos  Percentual de tributos do produto
     * @param  float  $percentualIss  Percentual de ISS do produto
     * @param  float  $percentualOutros  Percentual de outros impostos
     */
    public function calcularImpostosProduto(
        float $vgvSemTerrenista,
        float $percentualTributos,
        float $percentualIss,
        float $percentualOutros
    ): array {
        $valorImpostoProduto = $vgvSemTerrenista * ($percentualTributos / 100);

        // Distribuição padrão do imposto de tributos
        $pis = $valorImpostoProduto * 0.0925;
        $cofins = $valorImpostoProduto * 0.4275;
        $irpj = $valorImpostoProduto * 0.3150;
        $csll = $valorImpostoProduto * 0.1650;

        $iss = $vgvSemTerrenista * ($percentualIss / 100);
        $outrasDeducoes = $vgvSemTerrenista * ($percentualOutros / 100);

        return [
            'imposto_tributos' => round($valorImpostoProduto, 2),
            'imposto_pis' => round($pis, 2),
            'imposto_cofins' => round($cofins, 2),
            'imposto_iss' => round($iss, 2),
            'irrpj' => round($irpj, 2),
            'csll' => round($csll, 2),
            'outras_deducoes' => round($outrasDeducoes, 2),
        ];
    }

    /**
     * Calcula o custo de juros PJ (antecipação de recebíveis)
     *
     * @param  float  $valorObra  Valor total da obra
     * @param  int  $mesesPrazo  Prazo em meses
     * @param  string  $tipoJuros  'simples' ou 'composto'
     */
    public function calcularJurosPJ(
        float $valorObra,
        int $mesesPrazo,
        string $tipoJuros = 'composto',
        ?float $taxaAnual = null,
        ?float $percentualAntecipado = null,
        float $valorBaseAdicional = 0,
        int $carenciaMeses = 0,
        int $amortizacaoParcelas = 0
    ): array {
        $percentualAntecipado = $percentualAntecipado ?? (config('viabilidade.defaults.percentual_antecipacao_pj', 10) / 100);
        $taxaAnual = $taxaAnual ?? (config('viabilidade.defaults.taxa_juros_pj', 10.5) / 100);
        $taxaMensal = $tipoJuros === 'simples' ? ($taxaAnual / 12) : (pow(1 + $taxaAnual, 1 / 12) - 1);
        $carenciaMeses = max(0, $carenciaMeses);
        $amortizacaoParcelas = max(0, $amortizacaoParcelas);
        $prazoEfetivo = max(1, $mesesPrazo);
        $baseAntecipacao = max(0, $valorObra + max(0, $valorBaseAdicional));
        $valorAntecipado = $baseAntecipacao * max(0, $percentualAntecipado);
        $saldoDevedor = $valorAntecipado;
        $jurosTotais = 0.0;

        if ($carenciaMeses > 0) {
            for ($i = 0; $i < $carenciaMeses; $i++) {
                $jurosTotais += $saldoDevedor * $taxaMensal;
            }
        }

        if ($amortizacaoParcelas > 0) {
            $amortizacaoMensal = $valorAntecipado / $amortizacaoParcelas;
            for ($i = 0; $i < $amortizacaoParcelas; $i++) {
                $jurosMes = $saldoDevedor * $taxaMensal;
                $jurosTotais += $jurosMes;
                $saldoDevedor = max(0, $saldoDevedor - $amortizacaoMensal);
            }
            $prazoEfetivo = $carenciaMeses + $amortizacaoParcelas;
        } else {
            if ($tipoJuros === 'simples') {
                $jurosTotais += $valorAntecipado * $taxaMensal * $mesesPrazo;
            } else {
                $montante = $valorAntecipado * pow(1 + $taxaMensal, $mesesPrazo);
                $jurosTotais += ($montante - $valorAntecipado);
            }
            $prazoEfetivo = max(1, $carenciaMeses + $mesesPrazo);
        }

        $totalPagar = $valorAntecipado + $jurosTotais;

        return [
            'valor_obra' => $valorObra,
            'valor_antecipado' => round($valorAntecipado, 2),
            'taxa_mensal' => $taxaMensal,
            'prazo_meses' => $prazoEfetivo,
            'tipo_juros' => $tipoJuros,
            'carencia_meses' => $carenciaMeses,
            'amortizacao_parcelas' => $amortizacaoParcelas,
            'juros_totais' => round($jurosTotais, 2),
            'valor_total_pagar' => round($totalPagar, 2),
            'parcela_mensal' => round($totalPagar / $prazoEfetivo, 2),
        ];
    }
}
