<?php

declare(strict_types=1);

namespace App\Services\Tenant\Viabilidade\v1\Calculos;

use App\Services\Tenant\Viabilidade\v1\ImpostosService;
use Carbon\Carbon;

class IndicadoresCalculator
{
    private const PERCENTUAL_DEVOLUCAO_APORTE_MENSAL = 0.25;

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
     *   1: array<string, mixed>
     * }
     */
    public function calcularIndicadoresFinanceiros(array $fluxo, array $datas, array $params, array $dadosProdutos): array
    {
        $fluxoFinanceiro = [];
        $fluxoOperacionalTir = [];
        $fluxoFinanceiroTir = [];
        $saldoOperacional = 0.0;
        $saldoFinanceiro = 0.0;
        $saldoLivreEquity = 0.0;
        $paybackOperacionalMes = null;
        $paybackFinanceiroMes = null;
        $teveSaldoOperacionalNegativo = false;
        $teveSaldoFinanceiroNegativo = false;
        $taxaExposicaoMensal = pow(1 + ($params['taxaExposicaoAplicada'] ?? 0), 1 / 12) - 1;
        $exposicaoAplicadaTotal = 0.0;

        $totalUnidades = max(0, (int) ($dadosProdutos['totalUnidades'] ?? 0));
        $vgv = max(0.0, (float) ($dadosProdutos['vgv'] ?? 0.0));
        $custoTotalObra = (float) ($dadosProdutos['custoObraHabitacao'] ?? 0.0)
            + (float) ($dadosProdutos['custoInfraestrutura'] ?? 0.0)
            + (float) ($dadosProdutos['custoNaoIncidente'] ?? 0.0)
            + ((float) ($params['custoAreaComum'] ?? 0.0) * $totalUnidades)
            + ($vgv * (float) ($params['percentualContrapartidas'] ?? 0.0))
            + ((float) ($params['canteiroMensal'] ?? 0.0) * max(0, (int) ($params['mesesObra'] ?? 0)));
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
            ($params['dataAntecipacaoPj'] ?? null) instanceof \DateTimeInterface
                ? $params['dataAntecipacaoPj']
                : null,
        );

        $cronogramaPorMes = $cronogramaDivida['por_mes'];
        $pctDistribuicao = max(0.0, min(1.0, (float) ($params['distribuicaoLucrosPercentualObra'] ?? 0.0)));
        $politicaCaixa = $this->calcularPoliticaCaixa($fluxo, $cronogramaPorMes, $pctDistribuicao);

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

            $linhaDivida = $cronogramaPorMes[$mes] ?? null;
            $entradaAntecipacaoMes = $linhaDivida !== null
                ? $linhaDivida['desembolso']
                : 0.0;
            $pagamentoPjMes = $linhaDivida !== null
                ? $linhaDivida['juros_pagos'] + $linhaDivida['amortizacao']
                : 0.0;
            $jurosPjMes = $linhaDivida !== null ? $linhaDivida['juros_pagos'] : 0.0;
            $amortizacaoPjMes = $linhaDivida !== null ? $linhaDivida['amortizacao'] : 0.0;
            $saldoDividaPjMes = $linhaDivida !== null ? $linhaDivida['saldo_final'] : 0.0;

            // Vetor financeiro da planilha: fluxo operacional após funding e
            // serviço da dívida PJ, antes de aporte, distribuição e do custo
            // gerencial de exposição.
            $fluxoLivreEquityMes = $valorOperacional + $entradaAntecipacaoMes - $pagamentoPjMes;
            $saldoLivreEquity += $fluxoLivreEquityMes;
            if ($saldoLivreEquity < 0) {
                $teveSaldoFinanceiroNegativo = true;
            }

            $exposicaoAplicadaMes = 0.0;
            if ($dataAtual->lessThanOrEqualTo($datas['dataEntrega']) && $saldoLivreEquity < 0) {
                $exposicaoAplicadaMes = abs($saldoLivreEquity) * $taxaExposicaoMensal;
                $exposicaoAplicadaTotal += $exposicaoAplicadaMes;
            }

            $politicaMes = $politicaCaixa[$mes];
            $aporteMes = $politicaMes['aporte'];
            $devolucaoAporteMes = $politicaMes['devolucao_aporte'];
            $distribuicaoLucrosMes = $politicaMes['distribuicao_lucros'];
            $valorFinanceiroMes = $fluxoLivreEquityMes + $aporteMes - $devolucaoAporteMes - $distribuicaoLucrosMes;
            $saldoFinanceiro = $politicaMes['saldo_final'];

            $fluxoFinanceiro[$mes] = [
                'valor' => round($valorFinanceiroMes, 2),
                'saldo_acumulado' => round($saldoFinanceiro, 2),
                'aporte' => round($aporteMes, 2),
                'devolucao_aporte' => round($devolucaoAporteMes, 2),
                'distribuicao_lucros' => round($distribuicaoLucrosMes, 2),
                'saldo_apos_devolucao_aporte' => round($politicaMes['saldo_apos_devolucao_aporte'], 2),
                'caixa_minimo' => round($politicaMes['caixa_minimo'], 2),
                'entrada_antecipacao_pj' => round($entradaAntecipacaoMes, 2),
                'pagamento_pj' => round($pagamentoPjMes, 2),
                'juros_pj' => round($jurosPjMes, 2),
                'amortizacao_pj' => round($amortizacaoPjMes, 2),
                'saldo_divida_pj' => round($saldoDividaPjMes, 2),
                'exposicao_aplicada' => round($exposicaoAplicadaMes, 2),
                'fluxo_livre_equity' => round($fluxoLivreEquityMes, 2),
                'saldo_livre_equity_acumulado' => round($saldoLivreEquity, 2),
            ];
            $fluxoFinanceiroTir[] = ['data' => $dataAtual->copy(), 'valor' => $saldoLivreEquity];
            if ($paybackFinanceiroMes === null && $teveSaldoFinanceiroNegativo && $saldoLivreEquity >= 0) {
                $paybackFinanceiroMes = count($fluxoFinanceiroTir);
            }
        }

        // Estende o horizonte financeiro se o cronograma de dívida ultrapassar o fluxo operacional.
        foreach ($cronogramaPorMes as $mesDivida => $linhaDivida) {
            if (isset($fluxoFinanceiro[$mesDivida])) {
                continue;
            }
            $pagamento = $linhaDivida['juros_pagos'] + $linhaDivida['amortizacao'];
            if ($pagamento <= 0.0 && $linhaDivida['desembolso'] <= 0.0) {
                continue;
            }
            $dataAtual = Carbon::parse($mesDivida.'-01');
            $valorFinanceiroMes = $linhaDivida['desembolso'] - $pagamento;
            $saldoFinanceiro += $valorFinanceiroMes;
            $saldoLivreEquity += $valorFinanceiroMes;
            $fluxoFinanceiro[$mesDivida] = [
                'valor' => round($valorFinanceiroMes, 2),
                'saldo_acumulado' => round($saldoFinanceiro, 2),
                'aporte' => 0.0,
                'devolucao_aporte' => 0.0,
                'saldo_apos_devolucao_aporte' => round($saldoFinanceiro, 2),
                'caixa_minimo' => 0.0,
                'entrada_antecipacao_pj' => round($linhaDivida['desembolso'], 2),
                'pagamento_pj' => round($pagamento, 2),
                'juros_pj' => round($linhaDivida['juros_pagos'], 2),
                'amortizacao_pj' => round($linhaDivida['amortizacao'], 2),
                'saldo_divida_pj' => round($linhaDivida['saldo_final'], 2),
                'exposicao_aplicada' => 0.0,
                'distribuicao_lucros' => 0.0,
                'fluxo_livre_equity' => round($valorFinanceiroMes, 2),
                'saldo_livre_equity_acumulado' => round($saldoLivreEquity, 2),
            ];
            $fluxoFinanceiroTir[] = ['data' => $dataAtual->copy(), 'valor' => $saldoLivreEquity];
            if ($saldoLivreEquity < 0) {
                $teveSaldoFinanceiroNegativo = true;
            }
            if ($paybackFinanceiroMes === null && $teveSaldoFinanceiroNegativo && $saldoLivreEquity >= 0) {
                $paybackFinanceiroMes = count($fluxoFinanceiroTir);
            }
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
                'exposicao_maxima_financeira' => collect($fluxoFinanceiro)->min('saldo_livre_equity_acumulado'),
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

    /**
     * Reproduz as colunas JA:JO da planilha canônica: aporte do déficit
     * incremental, devolução do aporte, reserva de um mês de saídas e DL.
     *
     * @param  array<string, array<string, mixed>>  $fluxo
     * @param  array<string, array<string, float>>  $cronogramaPorMes
     * @return array<string, array{
     *   aporte: float,
     *   devolucao_aporte: float,
     *   distribuicao_lucros: float,
     *   saldo_apos_devolucao_aporte: float,
     *   caixa_minimo: float,
     *   saldo_final: float
     * }>
     */
    private function calcularPoliticaCaixa(array $fluxo, array $cronogramaPorMes, float $pctDistribuicao): array
    {
        $fluxosLivres = [];
        $aportes = [];
        $saldoLivreAcumulado = 0.0;
        $totalAportes = 0.0;

        foreach ($fluxo as $mes => $linha) {
            $linhaDivida = $cronogramaPorMes[$mes] ?? null;
            $fluxoLivre = (float) ($linha['saldo_mes'] ?? 0.0)
                + (float) ($linhaDivida['desembolso'] ?? 0.0)
                - (float) (($linhaDivida['juros_pagos'] ?? 0.0) + ($linhaDivida['amortizacao'] ?? 0.0));
            $saldoLivreAcumulado += $fluxoLivre;

            $aporteMes = $saldoLivreAcumulado < 0.0 ? max(0.0, -$fluxoLivre) : 0.0;
            $fluxosLivres[$mes] = $fluxoLivre;
            $aportes[$mes] = $aporteMes;
            $totalAportes += $aporteMes;
        }

        $politica = [];
        $aportesAcumulados = 0.0;
        $devolucoesAcumuladas = 0.0;
        $saldoAposAporte = 0.0;

        foreach ($fluxo as $mes => $linha) {
            $aporteMes = $aportes[$mes];
            $aportesAcumulados += $aporteMes;
            $saldoAposAporte += $fluxosLivres[$mes] + $aporteMes;

            $devolucaoMes = 0.0;
            if ($aportesAcumulados + 1e-7 >= $totalAportes) {
                $devolucaoCalculada = max(0.0, $saldoAposAporte * self::PERCENTUAL_DEVOLUCAO_APORTE_MENSAL);
                $devolucaoMes = min($devolucaoCalculada, max(0.0, $totalAportes - $devolucoesAcumuladas));
            }
            $devolucoesAcumuladas += $devolucaoMes;

            $politica[$mes] = [
                'aporte' => $aporteMes,
                'devolucao_aporte' => $devolucaoMes,
                'distribuicao_lucros' => 0.0,
                'saldo_apos_devolucao_aporte' => $saldoAposAporte - $devolucoesAcumuladas,
                'caixa_minimo' => max(0.0, (float) ($linha['despesas']['total'] ?? 0.0)),
                'saldo_final' => 0.0,
            ];
        }

        $saldoAposDevolucaoFinal = $politica !== []
            ? (float) end($politica)['saldo_apos_devolucao_aporte']
            : 0.0;
        $limiteDistribuicao = max(0.0, $saldoAposDevolucaoFinal) * $pctDistribuicao;
        $saldoAposCaixaMinimoAnterior = 0.0;
        $distribuicaoAlvoAcumulada = 0.0;
        $distribuicaoEfetivaAcumulada = 0.0;

        foreach ($politica as $mes => &$linhaPolitica) {
            $saldoAposCaixaMinimo = max(
                0.0,
                $linhaPolitica['saldo_apos_devolucao_aporte'] - $linhaPolitica['caixa_minimo'],
            );
            $incrementoDistribuivel = max(0.0, $saldoAposCaixaMinimo - $saldoAposCaixaMinimoAnterior);
            $distribuicaoAlvoAcumulada = min(
                $limiteDistribuicao,
                $distribuicaoAlvoAcumulada + $incrementoDistribuivel,
            );
            $novaDistribuicaoEfetivaAcumulada = min(
                $distribuicaoAlvoAcumulada,
                $linhaPolitica['saldo_apos_devolucao_aporte'],
            );
            $linhaPolitica['distribuicao_lucros'] = $novaDistribuicaoEfetivaAcumulada - $distribuicaoEfetivaAcumulada;
            $distribuicaoEfetivaAcumulada = $novaDistribuicaoEfetivaAcumulada;
            $linhaPolitica['saldo_final'] = $linhaPolitica['saldo_apos_devolucao_aporte'] - $distribuicaoEfetivaAcumulada;
            $saldoAposCaixaMinimoAnterior = $saldoAposCaixaMinimo;
        }
        unset($linhaPolitica);

        return $politica;
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
     * TIR anual via XIRR, sempre respeitando a quantidade real de dias.
     *
     * @param  list<array{data?: Carbon|\DateTimeInterface|string, valor: float|int}>  $fluxo
     */
    public function calcularTir(array $fluxo): ?float
    {
        $cashflows = [];
        $dates = [];

        foreach ($fluxo as $item) {
            $valor = (float) $item['valor'];
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

        return $this->resolverTaxa($npv, 1e-7);
    }

    /**
     * Procura todas as raízes no domínio econômico r > -100%.
     *
     * Fluxos não convencionais podem ter uma raiz negativa e outra positiva.
     * Nesse caso, usa a única raiz não negativa. Havendo mais de uma raiz não
     * negativa, a TIR é ambígua e retorna null em vez de escolher uma taxa
     * arbitrária.
     *
     * @param  callable(float): float  $npv
     */
    private function resolverTaxa(callable $npv, float $tolerancia): ?float
    {
        $logMinimo = log(0.000001);
        $logMaximo = log(1_000_001.0);
        $passos = 4096;
        $raizes = [];
        $logAnterior = null;
        $valorAnterior = null;

        for ($passo = 0; $passo <= $passos; $passo++) {
            $logAtual = $logMinimo + (($logMaximo - $logMinimo) * ($passo / $passos));
            $taxaAtual = exp($logAtual) - 1.0;
            $valorAtual = $npv($taxaAtual);

            if (! is_finite($valorAtual)) {
                $logAnterior = null;
                $valorAnterior = null;

                continue;
            }

            if (abs($valorAtual) <= $tolerancia) {
                $this->adicionarRaizUnica($raizes, $taxaAtual);
            } elseif ($logAnterior !== null && $valorAnterior !== null && $valorAnterior * $valorAtual < 0.0) {
                $logInferior = $logAnterior;
                $logSuperior = $logAtual;
                $valorInferior = $valorAnterior;

                for ($iteracao = 0; $iteracao < 120; $iteracao++) {
                    $logMeio = ($logInferior + $logSuperior) / 2.0;
                    $taxaMeio = exp($logMeio) - 1.0;
                    $valorMeio = $npv($taxaMeio);

                    if (! is_finite($valorMeio) || abs($valorMeio) <= $tolerancia) {
                        break;
                    }

                    if ($valorInferior * $valorMeio <= 0.0) {
                        $logSuperior = $logMeio;
                    } else {
                        $logInferior = $logMeio;
                        $valorInferior = $valorMeio;
                    }
                }

                $this->adicionarRaizUnica($raizes, exp(($logInferior + $logSuperior) / 2.0) - 1.0);
            }

            $logAnterior = $logAtual;
            $valorAnterior = $valorAtual;
        }

        sort($raizes);
        $raizesNaoNegativas = array_values(array_filter(
            $raizes,
            static fn (float $raiz): bool => $raiz >= -1e-10,
        ));

        if (count($raizesNaoNegativas) === 1) {
            return max(0.0, $raizesNaoNegativas[0]);
        }

        if (count($raizesNaoNegativas) > 1 || count($raizes) !== 1) {
            return null;
        }

        return $raizes[0];
    }

    /**
     * @param  list<float>  $raizes
     */
    private function adicionarRaizUnica(array &$raizes, float $novaRaiz): void
    {
        foreach ($raizes as $raiz) {
            $tolerancia = max(1e-7, abs($raiz) * 1e-7);
            if (abs($raiz - $novaRaiz) <= $tolerancia) {
                return;
            }
        }

        $raizes[] = $novaRaiz;
    }

    private function sanitizarTaxaAnual(?float $taxa): ?float
    {
        if ($taxa === null || ! is_finite($taxa)) {
            return null;
        }

        if ($taxa <= -1.0) {
            return null;
        }

        return $taxa;
    }
}
