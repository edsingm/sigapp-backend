<?php

namespace App\Services\Tenant\Viabilidade\v1\Calculos;

use App\Services\Tenant\Viabilidade\v1\ImpostosService;
use Carbon\Carbon;

class IndicadoresCalculator
{
    public function __construct(
        private readonly ImpostosService $impostosService,
    ) {}

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
        $jurosPJ = $this->impostosService->calcularJurosPJ(
            $custoTotalObra,
            $params['mesesObra'],
            'composto',
            $params['taxaJurosPj'],
            $params['percentualAntecipacaoPj'],
            $custoTerrenoBase,
            $params['carenciaPjMeses'],
            $params['amortizacaoPjParcelas']
        );
        $valorAntecipado = $jurosPJ['valor_antecipado'] ?? 0;
        $taxaMensalPj = $jurosPJ['taxa_mensal'] ?? 0;
        $saldoDevedorPj = $valorAntecipado;
        $amortizacaoParcelas = max(0, (int) ($params['amortizacaoPjParcelas'] ?? 0));
        $amortizacaoMensal = $amortizacaoParcelas > 0 ? ($valorAntecipado / $amortizacaoParcelas) : 0;
        $parcelaAmortizacaoAtual = 0;
        $inicioAmortizacao = $datas['dataEntrega']->copy()->addMonths(max(0, $params['carenciaPjMeses']) + 1)->startOfMonth();
        $taxaDevolucaoAporte = $params['devolucaoAportePercentual'] ?? 0;
        $totalAportes = max(0, ($params['aporteAdicionalMensal'] ?? 0) * ($params['mesesObra'] ?? 0));
        $devolucaoMensalAporte = ($params['mesesPosObra'] ?? 0) > 0 ? (($totalAportes * $taxaDevolucaoAporte) / $params['mesesPosObra']) : 0;

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

            $aporteMes = $dataAtual->between($datas['inicioObra'], $datas['fimObra']) ? (float) ($params['aporteAdicionalMensal'] ?? 0) : 0;
            $devolucaoAporteMes = $dataAtual->between($datas['inicioPos'], $datas['fimPos']) ? $devolucaoMensalAporte : 0;
            $entradaAntecipacaoMes = $dataAtual->format('Y-m') === $datas['inicioObra']->format('Y-m') ? $valorAntecipado : 0;

            $pagamentoPjMes = 0.0;
            if ($amortizacaoParcelas > 0 && $dataAtual->greaterThanOrEqualTo($inicioAmortizacao) && $parcelaAmortizacaoAtual < $amortizacaoParcelas) {
                $jurosMes = $saldoDevedorPj * $taxaMensalPj;
                $pagamentoPjMes = $jurosMes + $amortizacaoMensal;
                $saldoDevedorPj = max(0, $saldoDevedorPj - $amortizacaoMensal);
                $parcelaAmortizacaoAtual++;
            }

            $ajusteFinanceiroBase = $aporteMes - $devolucaoAporteMes + $entradaAntecipacaoMes - $pagamentoPjMes;
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
                'entrada_antecipacao_pj' => round($entradaAntecipacaoMes, 2),
                'pagamento_pj' => round($pagamentoPjMes, 2),
                'exposicao_aplicada' => round($exposicaoAplicadaMes, 2),
            ];
            $fluxoFinanceiroTir[] = ['data' => $dataAtual->copy(), 'valor' => $valorFinanceiroMes];
            if ($paybackFinanceiroMes === null && $teveSaldoFinanceiroNegativo && $saldoFinanceiro >= 0) {
                $paybackFinanceiroMes = count($fluxoFinanceiroTir);
            }
        }

        return [
            $fluxoFinanceiro,
            [
                'tir_financeira' => $this->calcularTir($fluxoFinanceiroTir),
                'exposicao_maxima_financeira' => collect($fluxoFinanceiro)->min('saldo_acumulado'),
                'exposicao_maxima_operacional' => collect($fluxo)->min('saldo_acumulado_mes'),
                'payback_operacional_meses' => $paybackOperacionalMes,
                'payback_financeiro_meses' => $paybackFinanceiroMes,
                'exposicao_aplicada_total' => round($exposicaoAplicadaTotal, 2),
            ],
        ];
    }

    public function calcularIndicadoresVso(array $fluxo, array $dadosProdutos): array
    {
        $unidadesConstrutora = max(1, (int) ($dadosProdutos['totalUnidadesConstrutora'] ?? $dadosProdutos['totalUnidades'] ?? 1));
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
            if ($mesAtingeEstoque === null && $vendasAcumuladas >= $unidadesConstrutora) {
                $mesAtingeEstoque = $mes;
            }
        }

        $vsoTotal = min(1, $vendasAcumuladas / $unidadesConstrutora);
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
            $media = count($somasMoveis) > 0 ? (array_sum($somasMoveis) / count($somasMoveis)) : 0;

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

    public function calcularTir(array $fluxo): float
    {
        $temPositivo = false;
        $temNegativo = false;
        foreach ($fluxo as $item) {
            if ($item['valor'] > 0) {
                $temPositivo = true;
            }
            if ($item['valor'] < 0) {
                $temNegativo = true;
            }
        }
        if (! $temPositivo || ! $temNegativo) {
            return 0;
        }

        foreach ([0.005, 0.01, 0.02, 0.05] as $estimativaInicial) {
            $taxa = $estimativaInicial;
            for ($i = 0; $i < 200; $i++) {
                $f = $df = 0;
                foreach ($fluxo as $t => $item) {
                    $fator = pow(1 + $taxa, $t);
                    $f += $item['valor'] / $fator;
                    $df -= $t * $item['valor'] / ($fator * (1 + $taxa));
                }
                if (abs($df) < 1e-10) {
                    break;
                }
                $proximaTaxa = $taxa - ($f / $df);
                if ($proximaTaxa <= -1) {
                    break;
                }
                if (abs($proximaTaxa - $taxa) < 1e-8) {
                    return pow(1 + $proximaTaxa, 12) - 1;
                }
                $taxa = $proximaTaxa;
            }
        }

        return 0;
    }
}
