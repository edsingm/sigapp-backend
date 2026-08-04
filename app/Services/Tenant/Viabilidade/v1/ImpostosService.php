<?php

namespace App\Services\Tenant\Viabilidade\v1;

use Carbon\Carbon;

/**
 * ImpostosService - Centraliza todos os cálculos de impostos e tributos
 *
 * Responsabilidades:
 * - Cálculo de PIS, COFINS, ISS
 * - Cálculo de IRPJ, CSLL
 * - Cálculo de tributos sobre receitas
 * - Proporção de impostos por produto
 * - Cronograma único da dívida PJ
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
     * Planilha: PIS/COFINS incide sobre Receita Bruta (VGV + juros/correção).
     * ISS e Outras Deduções incidem sobre VGV Venda (sem juros/correção).
     * IRPJ/CSLL são pré-calculados no processamento de produtos.
     *
     * @param  array  $produtos  Dados dos produtos processados
     * @param  float  $receitaBruta  Receita Bruta total (VGV + juros/correção)
     * @param  float  $vgvSemTerrenista  VGV sem valor do terrenista (base para ISS e Outras Deduções)
     * @return array Impostos detalhados
     */
    public function calcularImpostosDre(array $produtos, float $receitaBruta, float $vgvSemTerrenista): array
    {
        $pis = 0;
        $cofins = 0;
        $iss = 0;
        $irpj = 0;
        $csll = 0;
        $outrasDeducoes = 0;

        // Base de alocação única: VGV sem terrenista por produto (mesma base num/den).
        // Evita proporções > 100% quando há permuta (VGV bruto no num e líquido no den).
        $basesProduto = [];
        $somaBases = 0.0;
        foreach ($produtos as $idx => $produto) {
            $unidades = max(0, (int) ($produto['quantidade_unidades'] ?? 0));
            $permutas = max(0, (int) ($produto['permutas'] ?? 0));
            $comercializaveis = max(0, $unidades - $permutas);
            $preco = (float) ($produto['preco'] ?? 0);
            $pgto = (float) ($produto['pgto_por_lote'] ?? 0);
            $base = max(0.0, ($preco * $comercializaveis) - ($pgto * $comercializaveis));
            if ($base <= 0.0 && isset($produto['financeiro'])) {
                // fallback legado: vgv_produto líquido de permuta
                $base = max(0.0, (float) ($produto['vgv_produto'] ?? 0) - ($permutas * $preco) - ($unidades * $pgto));
            }
            $basesProduto[$idx] = $base;
            $somaBases += $base;
        }

        if ($somaBases <= 0.0 && $vgvSemTerrenista > 0) {
            $somaBases = $vgvSemTerrenista;
        }

        foreach ($produtos as $idx => $produto) {
            $proporcao = $somaBases > 0 ? ($basesProduto[$idx] / $somaBases) : 0.0;
            $receitaBrutaProduto = $receitaBruta * $proporcao;
            $vgvProdutoBase = $vgvSemTerrenista * $proporcao;

            // PIS/COFINS: base legal = receita bruta rateada
            $tributosPct = $produto['imposto_tributos'] ?? 0;
            $valorImposto = $receitaBrutaProduto * $tributosPct;
            $pis += $valorImposto * 0.0925;
            $cofins += $valorImposto * 0.4275;

            if (isset($produto['financeiro'])) {
                $issPct = (float) ($produto['imposto_iss'] ?? 0);
                $outrasPct = (float) ($produto['imposto_outros'] ?? 0);
                $iss += $vgvProdutoBase * $issPct;
                $outrasDeducoes += $vgvProdutoBase * $outrasPct;

                $tributosPctRaw = (float) ($produto['imposto_tributos'] ?? 0) * 100;
                if ($tributosPctRaw > 5) {
                    $irpj += $receitaBrutaProduto * 0.012;
                    $csll += $receitaBrutaProduto * 0.0108;
                } else {
                    $valorBaseIr = $receitaBrutaProduto * ((float) ($produto['imposto_tributos'] ?? 0));
                    $irpj += $valorBaseIr * 0.315;
                    $csll += $valorBaseIr * 0.165;
                }
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
            'base_alocacao' => 'vgv_sem_terrenista',
            'soma_proporcoes' => $somaBases > 0 ? 1.0 : 0.0,
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
        if ($taxaAnual === null) {
            throw new \InvalidArgumentException('taxaAnual é obrigatório para calcularJurosPJ (deve vir do $params)');
        }
        if ($percentualAntecipado === null) {
            throw new \InvalidArgumentException('percentualAntecipado é obrigatório para calcularJurosPJ (deve vir do $params)');
        }

        $taxaMensal = pow(1 + $taxaAnual, 1 / 12) - 1;
        $carenciaMeses = max(0, $carenciaMeses);
        $amortizacaoParcelas = max(0, $amortizacaoParcelas);

        // Antecipação: apenas sobre o custo da obra (planilha: 10% × obra)
        $baseAntecipacao = max(0, $valorObra);
        $valorAntecipado = $baseAntecipacao * max(0, $percentualAntecipado);

        // Juros simples durante obra + carência (planilha: taxa fixa todos os meses)
        $mesesSimples = $mesesPrazo + $carenciaMeses;

        // Fórmula planilha: juros = P × taxa × (meses_simples + (amortiz_parcelas + 1) / 2)
        $fatorJuros = $mesesSimples + ($amortizacaoParcelas > 0 ? ($amortizacaoParcelas + 1) / 2 : 0);
        $jurosTotais = $valorAntecipado * $taxaMensal * $fatorJuros;

        $totalPagar = $valorAntecipado + $jurosTotais;
        $prazoEfetivo = max(1, $mesesSimples + $amortizacaoParcelas);

        return [
            'valor_obra' => $valorObra,
            'valor_antecipado' => round($valorAntecipado, 2),
            'taxa_mensal' => $taxaMensal,
            'prazo_meses' => $prazoEfetivo,
            'tipo_juros' => 'planilha',
            'carencia_meses' => $carenciaMeses,
            'amortizacao_parcelas' => $amortizacaoParcelas,
            'juros_totais' => round($jurosTotais, 2),
            'valor_total_pagar' => round($totalPagar, 2),
            'parcela_mensal' => $totalPagar > 0 ? round(($amortizacaoParcelas > 0 ? $valorAntecipado / $amortizacaoParcelas : 0) + ($jurosTotais / max(1, $mesesSimples + $amortizacaoParcelas)), 2) : 0,
        ];
    }

    /**
     * Cronograma único da dívida PJ usado pela DRE e pelo fluxo financeiro.
     * Juros na carência são pagos (não capitalizados). Saldo final = 0.
     *
     * @return array{
     *   valor_antecipado: float,
     *   juros_totais: float,
     *   principal_amortizado: float,
     *   saldo_final: float,
     *   por_mes: array<string, array{desembolso: float, juros_pagos: float, amortizacao: float, saldo_inicial: float, saldo_final: float}>
     * }
     */
    public function gerarCronogramaDividaPj(
        float $valorObra,
        int $mesesObra,
        float $taxaAnual,
        float $percentualAntecipado,
        float $valorBaseAdicional,
        int $carenciaMeses,
        int $amortizacaoParcelas,
        Carbon|\DateTimeInterface $inicioObra,
        Carbon|\DateTimeInterface $dataEntrega,
        Carbon|\DateTimeInterface|null $dataDesembolso = null,
    ): array {
        unset($valorBaseAdicional);

        $resumo = $this->calcularJurosPJ(
            $valorObra,
            $mesesObra,
            'composto',
            $taxaAnual,
            $percentualAntecipado,
            0.0,
            $carenciaMeses,
            $amortizacaoParcelas,
        );

        $valorAntecipado = (float) $resumo['valor_antecipado'];
        $taxaMensal = (float) $resumo['taxa_mensal'];
        $porMes = [];

        if ($valorAntecipado <= 0.0) {
            return [
                'valor_antecipado' => 0.0,
                'juros_totais' => 0.0,
                'principal_amortizado' => 0.0,
                'saldo_final' => 0.0,
                'por_mes' => [],
            ];
        }

        $inicio = $inicioObra instanceof Carbon
            ? $inicioObra->copy()->startOfMonth()
            : Carbon::instance(\DateTime::createFromInterface($inicioObra))->startOfMonth();
        $entrega = $dataEntrega instanceof Carbon
            ? $dataEntrega->copy()->startOfMonth()
            : Carbon::instance(\DateTime::createFromInterface($dataEntrega))->startOfMonth();
        $desembolso = $dataDesembolso instanceof Carbon
            ? $dataDesembolso->copy()->startOfMonth()
            : ($dataDesembolso instanceof \DateTimeInterface
                ? Carbon::instance(\DateTime::createFromInterface($dataDesembolso))->startOfMonth()
                : $inicio->copy());

        $saldo = $valorAntecipado;
        $jurosTotais = 0.0;
        $principalAmortizado = 0.0;
        $amortizacaoMensal = $amortizacaoParcelas > 0 ? ($valorAntecipado / $amortizacaoParcelas) : 0.0;
        $inicioAmortizacao = $entrega->copy()->addMonths(max(0, $carenciaMeses))->startOfMonth();
        $parcelaAmort = 0;

        // A planilha libera o principal quando a demanda mínima é atingida e
        // cobra juros já nesse mês, ainda que a obra comece depois.
        $chaveDesembolso = $desembolso->format('Y-m');
        $jurosDesembolso = $saldo * $taxaMensal;
        $jurosTotais += $jurosDesembolso;
        $porMes[$chaveDesembolso] = [
            'desembolso' => round($valorAntecipado, 2),
            'juros_pagos' => round($jurosDesembolso, 2),
            'amortizacao' => 0.0,
            'saldo_inicial' => 0.0,
            'saldo_final' => round($saldo, 2),
        ];

        // Obra e carência: juros pagos mensalmente, sem capitalização.
        $cursor = $inicio->copy();
        $fimCarencia = $inicioAmortizacao->copy()->subMonth();
        while ($cursor->lessThanOrEqualTo($fimCarencia) && $saldo > 0.0) {
            $chave = $cursor->format('Y-m');
            if ($chave === $chaveDesembolso) {
                $cursor->addMonth();

                continue;
            }
            $juros = $saldo * $taxaMensal;
            $jurosTotais += $juros;
            $porMes[$chave] = [
                'desembolso' => 0.0,
                'juros_pagos' => round($juros, 2),
                'amortizacao' => 0.0,
                'saldo_inicial' => round($saldo, 2),
                'saldo_final' => round($saldo, 2),
            ];
            $cursor->addMonth();
        }

        // Amortização SAC: primeiro amortiza, depois calcula juros sobre o
        // saldo remanescente do próprio mês, como nas colunas IN/IP/IQ.
        while ($parcelaAmort < $amortizacaoParcelas && $saldo > 0.01) {
            $chave = $cursor->format('Y-m');
            $saldoInicial = $saldo;
            $amort = min($saldo, $amortizacaoMensal);
            if ($parcelaAmort === $amortizacaoParcelas - 1) {
                $amort = $saldo;
            }
            $saldo = max(0.0, $saldo - $amort);
            $juros = $saldo * $taxaMensal;
            $jurosTotais += $juros;
            $principalAmortizado += $amort;
            $parcelaAmort++;

            $existente = $porMes[$chave] ?? [
                'desembolso' => 0.0,
                'juros_pagos' => 0.0,
                'amortizacao' => 0.0,
                'saldo_inicial' => round($saldoInicial, 2),
                'saldo_final' => 0.0,
            ];
            $existente['juros_pagos'] = round((float) $existente['juros_pagos'] + $juros, 2);
            $existente['amortizacao'] = round((float) $existente['amortizacao'] + $amort, 2);
            $existente['saldo_inicial'] = round($saldoInicial, 2);
            $existente['saldo_final'] = round($saldo, 2);
            $porMes[$chave] = $existente;

            $cursor->addMonth();
        }

        // Garante quitação residual.
        if ($saldo > 0.01) {
            $chave = $cursor->format('Y-m');
            $saldoInicial = $saldo;
            $juros = $saldo * $taxaMensal;
            $jurosTotais += $juros;
            $amort = $saldo;
            $principalAmortizado += $amort;
            $saldo = 0.0;
            $porMes[$chave] = [
                'desembolso' => 0.0,
                'juros_pagos' => round($juros, 2),
                'amortizacao' => round($amort, 2),
                'saldo_inicial' => round($saldoInicial, 2),
                'saldo_final' => 0.0,
            ];
        }

        ksort($porMes);

        return [
            'valor_antecipado' => round($valorAntecipado, 2),
            'juros_totais' => round($jurosTotais, 2),
            'principal_amortizado' => round($principalAmortizado, 2),
            'saldo_final' => round($saldo, 2),
            'por_mes' => $porMes,
        ];
    }

    /**
     * Cronograma do Plano Empresário: o principal é liberado gradualmente
     * conforme a curva financeira de medição da obra. Juros são pagos sobre o
     * saldo efetivamente desembolsado e a amortização SAC começa após entrega
     * e carência.
     *
     * @param  list<float|int>  $curvaMedicao
     * @return array{
     *   valor_antecipado: float,
     *   juros_totais: float,
     *   principal_amortizado: float,
     *   saldo_final: float,
     *   por_mes: array<string, array{desembolso: float, juros_pagos: float, amortizacao: float, saldo_inicial: float, saldo_final: float}>
     * }
     */
    public function gerarCronogramaDividaPjPorMedicao(
        float $valorObra,
        float $taxaAnual,
        float $percentualFinanciado,
        int $carenciaMeses,
        int $amortizacaoParcelas,
        Carbon|\DateTimeInterface $inicioObra,
        Carbon|\DateTimeInterface $dataEntrega,
        array $curvaMedicao,
    ): array {
        $principalTotal = max(0.0, $valorObra) * max(0.0, min(1.0, $percentualFinanciado));

        if ($principalTotal <= 0.0) {
            return [
                'valor_antecipado' => 0.0,
                'juros_totais' => 0.0,
                'principal_amortizado' => 0.0,
                'saldo_final' => 0.0,
                'por_mes' => [],
            ];
        }

        $inicio = $inicioObra instanceof Carbon
            ? $inicioObra->copy()->startOfMonth()
            : Carbon::instance(\DateTime::createFromInterface($inicioObra))->startOfMonth();
        $entrega = $dataEntrega instanceof Carbon
            ? $dataEntrega->copy()->startOfMonth()
            : Carbon::instance(\DateTime::createFromInterface($dataEntrega))->startOfMonth();
        $taxaMensal = max(0.0, $taxaAnual) > 0.0
            ? pow(1 + max(0.0, $taxaAnual), 1 / 12) - 1
            : 0.0;
        $percentuais = array_map(
            static fn (float|int $percentual): float => max(0.0, (float) $percentual),
            $curvaMedicao,
        );
        $somaPercentuais = array_sum($percentuais);

        if ($somaPercentuais <= 0.0) {
            $percentuais = [100.0];
            $somaPercentuais = 100.0;
        }

        $porMes = [];
        $saldo = 0.0;
        $jurosTotais = 0.0;
        $desembolsado = 0.0;
        $ultimoIndice = array_key_last($percentuais);

        foreach ($percentuais as $indice => $percentual) {
            $desembolso = $indice === $ultimoIndice
                ? max(0.0, $principalTotal - $desembolsado)
                : $principalTotal * ($percentual / $somaPercentuais);
            $saldoInicial = $saldo;
            $saldo += $desembolso;
            $desembolsado += $desembolso;
            $juros = $saldo * $taxaMensal;
            $jurosTotais += $juros;
            $mes = $inicio->copy()->addMonths((int) $indice)->format('Y-m');
            $porMes[$mes] = [
                'desembolso' => round($desembolso, 2),
                'juros_pagos' => round($juros, 2),
                'amortizacao' => 0.0,
                'saldo_inicial' => round($saldoInicial, 2),
                'saldo_final' => round($saldo, 2),
            ];
        }

        $inicioAmortizacao = $entrega->copy()->addMonths(max(0, $carenciaMeses))->startOfMonth();
        $cursor = $inicio->copy()->addMonths(count($percentuais));

        while ($cursor->lessThan($inicioAmortizacao) && $saldo > 0.0) {
            $mes = $cursor->format('Y-m');
            $juros = $saldo * $taxaMensal;
            $jurosTotais += $juros;
            $porMes[$mes] = [
                'desembolso' => 0.0,
                'juros_pagos' => round($juros, 2),
                'amortizacao' => 0.0,
                'saldo_inicial' => round($saldo, 2),
                'saldo_final' => round($saldo, 2),
            ];
            $cursor->addMonth();
        }

        if ($cursor->lessThan($inicioAmortizacao)) {
            $cursor = $inicioAmortizacao->copy();
        }

        $amortizacaoParcelas = max(0, $amortizacaoParcelas);
        $amortizacaoMensal = $amortizacaoParcelas > 0 ? $principalTotal / $amortizacaoParcelas : 0.0;
        $principalAmortizado = 0.0;

        for ($parcela = 0; $parcela < $amortizacaoParcelas && $saldo > 0.01; $parcela++) {
            $saldoInicial = $saldo;
            $amortizacao = $parcela === $amortizacaoParcelas - 1
                ? $saldo
                : min($saldo, $amortizacaoMensal);
            $saldo = max(0.0, $saldo - $amortizacao);
            $principalAmortizado += $amortizacao;
            $juros = $saldo * $taxaMensal;
            $jurosTotais += $juros;
            $mes = $cursor->format('Y-m');
            $porMes[$mes] = [
                'desembolso' => 0.0,
                'juros_pagos' => round($juros, 2),
                'amortizacao' => round($amortizacao, 2),
                'saldo_inicial' => round($saldoInicial, 2),
                'saldo_final' => round($saldo, 2),
            ];
            $cursor->addMonth();
        }

        if ($saldo > 0.01) {
            $saldoInicial = $saldo;
            $juros = $saldo * $taxaMensal;
            $jurosTotais += $juros;
            $principalAmortizado += $saldo;
            $mes = $cursor->format('Y-m');
            $porMes[$mes] = [
                'desembolso' => 0.0,
                'juros_pagos' => round($juros, 2),
                'amortizacao' => round($saldo, 2),
                'saldo_inicial' => round($saldoInicial, 2),
                'saldo_final' => 0.0,
            ];
            $saldo = 0.0;
        }

        ksort($porMes);

        return [
            'valor_antecipado' => round($principalTotal, 2),
            'juros_totais' => round($jurosTotais, 2),
            'principal_amortizado' => round($principalAmortizado, 2),
            'saldo_final' => round($saldo, 2),
            'por_mes' => $porMes,
        ];
    }
}
