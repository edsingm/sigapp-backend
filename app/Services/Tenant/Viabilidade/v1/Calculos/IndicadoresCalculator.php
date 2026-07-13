<?php

declare(strict_types=1);

namespace App\Services\Tenant\Viabilidade\v1\Calculos;

use App\Services\Tenant\Viabilidade\v1\ImpostosService;
use Carbon\Carbon;

class IndicadoresCalculator
{
    public function __construct(
        private readonly ImpostosService $impostosService,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $fluxo
     * @param  array<string, Carbon>  $datas
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $dadosProdutos
     * @return array{
     *   0: array<string, array<string, float|int>>,
     *   1: array<string, float|int|string|null>
     * }
     */
    public function calcularIndicadoresFinanceiros(array $fluxo, array $datas, array $params, array $dadosProdutos): array
    {
        $fluxoFinanceiro = [];
        $fluxoOperacionalTir = [];
        $fluxoFinanceiroTir = [];
        $saldoOperacional = 0.0;
        $saldoFinanceiro = 0.0;
        $paybackOperacionalMes = null;
        $paybackFinanceiroMes = null;
        $teveSaldoOperacionalNegativo = false;
        $teveSaldoFinanceiroNegativo = false;
        $taxaExposicaoMensal = pow(1 + ($params['taxaExposicaoAplicada'] ?? 0), 1 / 12) - 1;
        $exposicaoAplicadaTotal = 0.0;

        $custoTotalObra = ($dadosProdutos['custoObraHabitacao'] ?? 0) + ($dadosProdutos['custoInfraestrutura'] ?? 0);
        $custoTerrenoBase = ($dadosProdutos['permutas'] * ($dadosProdutos['produtos'][0]['preco'] ?? 0)) + ($params['compraTerreno'] ?? 0);

        $cronogramaDivida = $this->impostosService->gerarCronogramaDividaPj(
            (float) $custoTotalObra,
            (int) ($params['mesesObra'] ?? 0),
            (float) ($params['taxaJurosPj'] ?? 0),
            (float) ($params['percentualAntecipacaoPj'] ?? 0),
            (float) $custoTerrenoBase,
            (int) ($params['carenciaPjMeses'] ?? 0),
            (int) ($params['amortizacaoPjParcelas'] ?? 0),
            $datas['inicioObra']->copy()->startOfMonth(),
            $datas['dataEntrega']->copy()->startOfMonth(),
        );

        $cronogramaPorMes = $cronogramaDivida['por_mes'];
        $taxaDevolucaoAporte = $params['devolucaoAportePercentual'] ?? 0;
        $totalAportes = max(0, ($params['aporteAdicionalMensal'] ?? 0) * ($params['mesesObra'] ?? 0));
        $devolucaoMensalAporte = ($params['mesesPosObra'] ?? 0) > 0
            ? (($totalAportes * $taxaDevolucaoAporte) / $params['mesesPosObra'])
            : 0;

        $valorAntecipado = (float) ($cronogramaDivida['valor_antecipado'] ?? 0);

        foreach ($fluxo as $mes => $linha) {
            $dataAtual = Carbon::parse($mes.'-01');
            $valorOperacional = (float) ($linha['saldo_mes'] ?? 0);
            $fluxoOperacionalTir[] = ['data' => $dataAtual->copy(), 'valor' => $valorOperacional];
            $saldoOperacional += $valorOperacional;
            if ($saldoOperacional < 0) {
                $teveSaldoOperacionalNegativo = true;
            }
            if ($paybackOperacionalMes === null && $teveSaldoOperacionalNegativo && $saldoOperacional >= 0) {
                $paybackOperacionalMes = count($fluxoOperacionalTir);
            }

            $aporteMes = $dataAtual->between($datas['inicioObra'], $datas['fimObra'])
                ? (float) ($params['aporteAdicionalMensal'] ?? 0)
                : 0;
            $devolucaoAporteMes = $dataAtual->between($datas['inicioPos'], $datas['fimPos'])
                ? $devolucaoMensalAporte
                : 0;

            // Distribuição de lucros durante a obra: % do saldo operacional positivo.
            $distribuicaoLucrosMes = 0.0;
            $pctDistribuicao = max(0.0, min(1.0, (float) ($params['distribuicaoLucrosPercentualObra'] ?? 0.0)));
            if ($pctDistribuicao > 0.0
                && $dataAtual->between($datas['inicioObra'], $datas['fimObra'])
                && $valorOperacional > 0.0
            ) {
                $distribuicaoLucrosMes = $valorOperacional * $pctDistribuicao;
            }

            $linhaDivida = $cronogramaPorMes[$mes] ?? null;
            $entradaAntecipacaoMes = $linhaDivida !== null
                ? (float) ($linhaDivida['desembolso'] ?? 0)
                : ($dataAtual->format('Y-m') === $datas['inicioObra']->format('Y-m') ? $valorAntecipado : 0);
            $pagamentoPjMes = $linhaDivida !== null
                ? (float) ($linhaDivida['juros_pagos'] ?? 0) + (float) ($linhaDivida['amortizacao'] ?? 0)
                : 0.0;

            $ajusteFinanceiroBase = $aporteMes - $devolucaoAporteMes + $entradaAntecipacaoMes - $pagamentoPjMes - $distribuicaoLucrosMes;
            $valorFinanceiroMes = $valorOperacional + $ajusteFinanceiroBase;
            $saldoFinanceiro += $valorFinanceiroMes;

            $exposicaoAplicadaMes = 0.0;
            if ($dataAtual->lessThanOrEqualTo($datas['dataEntrega']) && $saldoFinanceiro < 0) {
                $exposicaoAplicadaMes = abs($saldoFinanceiro) * $taxaExposicaoMensal;
                $valorFinanceiroMes -= $exposicaoAplicadaMes;
                $saldoFinanceiro -= $exposicaoAplicadaMes;
                $exposicaoAplicadaTotal += $exposicaoAplicadaMes;
            }
            if ($saldoFinanceiro < 0) {
                $teveSaldoFinanceiroNegativo = true;
            }

            $fluxoFinanceiro[$mes] = [
                'valor' => round($valorFinanceiroMes, 2),
                'saldo_acumulado' => round($saldoFinanceiro, 2),
                'aporte' => round($aporteMes, 2),
                'devolucao_aporte' => round($devolucaoAporteMes, 2),
                'distribuicao_lucros' => round($distribuicaoLucrosMes, 2),
                'entrada_antecipacao_pj' => round($entradaAntecipacaoMes, 2),
                'pagamento_pj' => round($pagamentoPjMes, 2),
                'juros_pj' => round((float) ($linhaDivida['juros_pagos'] ?? 0), 2),
                'amortizacao_pj' => round((float) ($linhaDivida['amortizacao'] ?? 0), 2),
                'saldo_divida_pj' => round((float) ($linhaDivida['saldo_final'] ?? 0), 2),
                'exposicao_aplicada' => round($exposicaoAplicadaMes, 2),
            ];
            $fluxoFinanceiroTir[] = ['data' => $dataAtual->copy(), 'valor' => $valorFinanceiroMes];
            if ($paybackFinanceiroMes === null && $teveSaldoFinanceiroNegativo && $saldoFinanceiro >= 0) {
                $paybackFinanceiroMes = count($fluxoFinanceiroTir);
            }
        }

        // Estende o horizonte financeiro se o cronograma de dívida ultrapassar o fluxo operacional.
        foreach ($cronogramaPorMes as $mesDivida => $linhaDivida) {
            if (isset($fluxoFinanceiro[$mesDivida])) {
                continue;
            }
            $pagamento = (float) ($linhaDivida['juros_pagos'] ?? 0) + (float) ($linhaDivida['amortizacao'] ?? 0);
            if ($pagamento <= 0.0 && (float) ($linhaDivida['desembolso'] ?? 0) <= 0.0) {
                continue;
            }
            $dataAtual = Carbon::parse($mesDivida.'-01');
            $valorFinanceiroMes = (float) ($linhaDivida['desembolso'] ?? 0) - $pagamento;
            $saldoFinanceiro += $valorFinanceiroMes;
            $fluxoFinanceiro[$mesDivida] = [
                'valor' => round($valorFinanceiroMes, 2),
                'saldo_acumulado' => round($saldoFinanceiro, 2),
                'aporte' => 0.0,
                'devolucao_aporte' => 0.0,
                'entrada_antecipacao_pj' => round((float) ($linhaDivida['desembolso'] ?? 0), 2),
                'pagamento_pj' => round($pagamento, 2),
                'juros_pj' => round((float) ($linhaDivida['juros_pagos'] ?? 0), 2),
                'amortizacao_pj' => round((float) ($linhaDivida['amortizacao'] ?? 0), 2),
                'saldo_divida_pj' => round((float) ($linhaDivida['saldo_final'] ?? 0), 2),
                'exposicao_aplicada' => 0.0,
            ];
            $fluxoFinanceiroTir[] = ['data' => $dataAtual->copy(), 'valor' => $valorFinanceiroMes];
        }

        $tirFinanceiraAnual = $this->calcularTir($fluxoFinanceiroTir);
        $tirFinanceiraMensal = $tirFinanceiraAnual !== null
            ? $this->calcularTaxaMensalEquivalente($tirFinanceiraAnual)
            : null;

        return [
            $fluxoFinanceiro,
            [
                'tir_financeira' => $tirFinanceiraAnual,
                'tir_financeira_am_percentual' => $tirFinanceiraMensal !== null ? round($tirFinanceiraMensal * 100, 2) : null,
                'tir_financeira_aa_percentual' => $tirFinanceiraAnual !== null ? round($tirFinanceiraAnual * 100, 2) : null,
                'exposicao_maxima_financeira' => collect($fluxoFinanceiro)->min('saldo_acumulado'),
                'exposicao_maxima_operacional' => collect($fluxo)->min('saldo_acumulado_mes'),
                'payback_operacional_meses' => $paybackOperacionalMes,
                'payback_financeiro_meses' => $paybackFinanceiroMes,
                'exposicao_aplicada_total' => round($exposicaoAplicadaTotal, 2),
                'divida_pj' => [
                    'valor_antecipado' => $cronogramaDivida['valor_antecipado'],
                    'juros_totais' => $cronogramaDivida['juros_totais'],
                    'principal_amortizado' => $cronogramaDivida['principal_amortizado'],
                    'saldo_final' => $cronogramaDivida['saldo_final'],
                    'meses' => count($cronogramaPorMes),
                ],
            ],
        ];
    }

    private function calcularTaxaMensalEquivalente(float $taxaAnual): float
    {
        if ($taxaAnual <= -1.0) {
            return 0.0;
        }

        return pow(1 + $taxaAnual, 1 / 12) - 1;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fluxo
     * @param  array<string, mixed>  $dadosProdutos
     * @return array<string, float|int|string|null>
     */
    public function calcularIndicadoresVso(array $fluxo, array $dadosProdutos): array
    {
        $unidadesConstrutora = max(0, (int) ($dadosProdutos['totalUnidadesConstrutora'] ?? $dadosProdutos['totalUnidades'] ?? 0));
        $divisor = max(1, $unidadesConstrutora);
        $vendasAcumuladas = 0.0;
        $mesesComVenda = 0;
        $mesAtingeEstoque = null;
        $linhaMesVsoMaximo = null;
        $vsoMensalMaximo = 0.0;

        foreach ($fluxo as $mes => $linha) {
            $vendasMes = (float) ($linha['unidades_vendidas'] ?? 0);
            if ($vendasMes > 0) {
                $mesesComVenda++;
            }
            $vendasAcumuladas += $vendasMes;
            $estoqueRemanescente = max(0.0, $unidadesConstrutora - ($vendasAcumuladas - $vendasMes));
            $vsoMensal = $estoqueRemanescente > 0 ? ($vendasMes / $estoqueRemanescente) : 0.0;
            if ($vsoMensal > $vsoMensalMaximo) {
                $vsoMensalMaximo = $vsoMensal;
                $linhaMesVsoMaximo = $mes;
            }
            if ($mesAtingeEstoque === null && $unidadesConstrutora > 0 && $vendasAcumuladas >= $unidadesConstrutora) {
                $mesAtingeEstoque = $mes;
            }
        }

        $vsoTotal = min(1, $vendasAcumuladas / $divisor);
        $vsoMedioMensal = $mesesComVenda > 0 ? ($vsoTotal / $mesesComVenda) : 0.0;

        return [
            'vso_total_percentual' => round($vsoTotal * 100, 2),
            'vso_medio_mensal_percentual' => round($vsoMedioMensal * 100, 2),
            'vso_mensal_maximo_percentual' => round($vsoMensalMaximo * 100, 2),
            'vso_mes_maximo' => $linhaMesVsoMaximo,
            'vso_mes_zeragem_estoque' => $mesAtingeEstoque,
            'unidades_vendidas_acumuladas' => round($vendasAcumuladas, 2),
            'unidades_estoque_final' => round(max(0, $unidadesConstrutora - $vendasAcumuladas), 2),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $fluxo
     * @param  array<string, mixed>  $dadosProdutos
     * @return array{vso_janelas: array<string, array<string, float|int>>}
     */
    public function calcularIndicadoresVsoJanelas(array $fluxo, array $dadosProdutos): array
    {
        $unidadesConstrutora = max(1, (int) ($dadosProdutos['totalUnidadesConstrutora'] ?? $dadosProdutos['totalUnidades'] ?? 1));
        $vendasMensais = [];
        foreach ($fluxo as $linha) {
            $vendasMensais[] = max(0.0, (float) ($linha['unidades_vendidas'] ?? 0));
        }

        $janelas = [3, 6, 12];
        $resultado = [];

        foreach ($janelas as $janela) {
            $somasMoveis = [];
            $totalRegistros = count($vendasMensais);
            if ($totalRegistros === 0) {
                $resultado[$janela.'m'] = [
                    'ultimo_percentual' => 0,
                    'maximo_percentual' => 0,
                    'media_percentual' => 0,
                ];

                continue;
            }

            for ($i = 0; $i < $totalRegistros; $i++) {
                $inicio = max(0, $i - $janela + 1);
                $slice = array_slice($vendasMensais, $inicio, $i - $inicio + 1);
                $somasMoveis[] = array_sum($slice);
            }

            $ultimo = end($somasMoveis) ?: 0;
            $maximo = max($somasMoveis);
            $media = array_sum($somasMoveis) / count($somasMoveis);

            $resultado[$janela.'m'] = [
                'ultimo_percentual' => round(($ultimo / $unidadesConstrutora) * 100, 2),
                'maximo_percentual' => round(($maximo / $unidadesConstrutora) * 100, 2),
                'media_percentual' => round(($media / $unidadesConstrutora) * 100, 2),
            ];
        }

        return [
            'vso_janelas' => $resultado,
        ];
    }

    /**
     * TIR anual. Usa XIRR com datas reais; se as datas forem mensais no mesmo dia,
     * cai no IRR posicional mensal anualizado: (1 + r_m)^12 - 1.
     *
     * @param  list<array{data?: Carbon|\DateTimeInterface|string, valor: float|int}>  $fluxo
     */
    public function calcularTir(array $fluxo): ?float
    {
        $cashflows = [];
        $dates = [];

        foreach ($fluxo as $item) {
            $valor = (float) ($item['valor'] ?? 0);
            $data = $item['data'] ?? null;
            if ($data instanceof Carbon) {
                $carbon = $data->copy()->startOfDay();
            } elseif ($data instanceof \DateTimeInterface) {
                $carbon = Carbon::instance(\DateTime::createFromInterface($data))->startOfDay();
            } elseif (is_string($data) && $data !== '') {
                $carbon = Carbon::parse($data)->startOfDay();
            } else {
                $carbon = Carbon::create(2000, 1, 1)->addMonths(count($cashflows))->startOfDay();
            }

            // Ignora zeros no início e fim do vetor (não alteram a TIR e atrapalham o solver).
            $cashflows[] = $valor;
            $dates[] = $carbon;
        }

        // Remove prefixo/sufixo de zeros
        while ($cashflows !== [] && abs($cashflows[0]) < 1e-9) {
            array_shift($cashflows);
            array_shift($dates);
        }
        while ($cashflows !== [] && abs($cashflows[array_key_last($cashflows)]) < 1e-9) {
            array_pop($cashflows);
            array_pop($dates);
        }

        if (count($cashflows) < 2) {
            return null;
        }

        $temPositivo = false;
        $temNegativo = false;
        foreach ($cashflows as $valor) {
            if ($valor > 1e-9) {
                $temPositivo = true;
            }
            if ($valor < -1e-9) {
                $temNegativo = true;
            }
        }

        if (! $temPositivo || ! $temNegativo) {
            return null;
        }

        $mensalEquidistante = $this->datasSaoMensaisEquidistantes($dates);
        if ($mensalEquidistante) {
            $tirMensal = $this->irrPosicional($cashflows);
            if ($tirMensal === null) {
                return null;
            }

            $anual = pow(1 + $tirMensal, 12) - 1;

            return $this->sanitizarTaxaAnual($anual);
        }

        $xirr = $this->xirr($cashflows, $dates);

        return $this->sanitizarTaxaAnual($xirr);
    }

    /**
     * @param  list<float>  $cashflows
     * @param  list<Carbon>  $dates
     */
    private function xirr(array $cashflows, array $dates): ?float
    {
        $base = $dates[0];
        $anos = [];
        foreach ($dates as $date) {
            $dias = $base->diffInDays($date, false);
            $anos[] = $dias / 365.0;
        }

        $npv = static function (float $taxa) use ($cashflows, $anos): float {
            $total = 0.0;
            foreach ($cashflows as $t => $valor) {
                $den = pow(1 + $taxa, $anos[$t]);
                if ($den == 0.0 || ! is_finite($den)) {
                    return INF;
                }
                $total += $valor / $den;
            }

            return $total;
        };

        // Bisseção em faixa realista de taxas anuais.
        $low = -0.99;
        $high = 5.0;
        $fLow = $npv($low);
        $fHigh = $npv($high);
        if (! is_finite($fLow) || ! is_finite($fHigh) || $fLow * $fHigh > 0) {
            return null;
        }

        for ($i = 0; $i < 120; $i++) {
            $mid = ($low + $high) / 2;
            $fMid = $npv($mid);
            if (! is_finite($fMid) || abs($fMid) < 1e-7) {
                return $mid;
            }
            if ($fLow * $fMid <= 0) {
                $high = $mid;
                $fHigh = $fMid;
            } else {
                $low = $mid;
                $fLow = $fMid;
            }
        }

        return ($low + $high) / 2;
    }

    /**
     * @param  list<float>  $cashflows
     */
    private function irrPosicional(array $cashflows): ?float
    {
        $npv = static function (float $taxa) use ($cashflows): float {
            $total = 0.0;
            foreach ($cashflows as $t => $valor) {
                $den = pow(1 + $taxa, $t);
                if ($den == 0.0 || ! is_finite($den)) {
                    return INF;
                }
                $total += $valor / $den;
            }

            return $total;
        };

        $low = -0.99;
        $high = 5.0;
        $fLow = $npv($low);
        $fHigh = $npv($high);
        if (! is_finite($fLow) || ! is_finite($fHigh) || $fLow * $fHigh > 0) {
            return null;
        }

        for ($i = 0; $i < 120; $i++) {
            $mid = ($low + $high) / 2;
            $fMid = $npv($mid);
            if (! is_finite($fMid) || abs($fMid) < 1e-8) {
                return $mid;
            }
            if ($fLow * $fMid <= 0) {
                $high = $mid;
                $fHigh = $fMid;
            } else {
                $low = $mid;
                $fLow = $fMid;
            }
        }

        return ($low + $high) / 2;
    }

    /**
     * @param  list<Carbon>  $dates
     */
    private function datasSaoMensaisEquidistantes(array $dates): bool
    {
        if (count($dates) < 2) {
            return true;
        }

        for ($i = 1; $i < count($dates); $i++) {
            $prev = $dates[$i - 1];
            $curr = $dates[$i];
            if ($prev->day !== $curr->day) {
                return false;
            }
            $esperado = $prev->copy()->addMonthNoOverflow();
            if ($esperado->format('Y-m-d') !== $curr->format('Y-m-d')) {
                // permite 1 dia de folga por meses curtos
                if (abs($esperado->diffInDays($curr, false)) > 1) {
                    return false;
                }
            }
        }

        return true;
    }

    private function sanitizarTaxaAnual(?float $taxa): ?float
    {
        if ($taxa === null || ! is_finite($taxa)) {
            return null;
        }

        // Fora de [-99%, 500%] a.a. considera-se solução numérica inválida.
        if ($taxa <= -0.99 || $taxa > 5.0) {
            return null;
        }

        return $taxa;
    }
}
