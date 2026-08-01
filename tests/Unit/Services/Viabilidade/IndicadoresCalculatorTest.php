<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Viabilidade;

use App\Services\Tenant\Viabilidade\v1\Calculos\IndicadoresCalculator;
use App\Services\Tenant\Viabilidade\v1\ImpostosService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class IndicadoresCalculatorTest extends TestCase
{
    private IndicadoresCalculator $calculator;

    private ImpostosService $impostos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->impostos = new ImpostosService;
        $this->calculator = new IndicadoresCalculator($this->impostos);
    }

    public function test_xirr_conhecido_com_datas_mensais(): void
    {
        // Investimento -100 e 12 retornos de 10 => TIR mensal ~1,6% a.m. (aprox)
        $fluxo = [
            ['data' => Carbon::parse('2020-01-01'), 'valor' => -100.0],
        ];
        for ($i = 1; $i <= 12; $i++) {
            $fluxo[] = ['data' => Carbon::parse('2020-01-01')->addMonths($i), 'valor' => 10.0];
        }

        $tir = $this->calculator->calcularTir($fluxo);

        $this->assertNotNull($tir);
        $this->assertGreaterThan(0.0, $tir);
        // Anualidade razoável (IRR mensal positiva)
        $this->assertLessThan(1.0, $tir);
    }

    public function test_tir_sem_mudanca_de_sinal_retorna_null(): void
    {
        $fluxo = [
            ['data' => Carbon::parse('2020-01-01'), 'valor' => 10.0],
            ['data' => Carbon::parse('2020-02-01'), 'valor' => 20.0],
        ];

        $this->assertNull($this->calculator->calcularTir($fluxo));
    }

    public function test_xirr_mensal_usa_dias_reais_e_nao_intervalos_posicionais(): void
    {
        $fluxo = [
            ['data' => Carbon::parse('2030-01-01'), 'valor' => -100.0],
            ['data' => Carbon::parse('2030-02-01'), 'valor' => 120.0],
        ];

        $tir = $this->calculator->calcularTir($fluxo);

        $this->assertNotNull($tir);
        $this->assertEqualsWithDelta(pow(1.2, 365 / 31) - 1, $tir, 0.000001);
    }

    public function test_tir_encontra_raiz_positiva_quando_ha_saida_residual_no_fim(): void
    {
        $fluxo = [
            ['data' => Carbon::parse('2030-01-01'), 'valor' => -100.0],
            ['data' => Carbon::parse('2030-02-01'), 'valor' => 120.0],
        ];
        for ($mes = 2; $mes < 12; $mes++) {
            $fluxo[] = ['data' => Carbon::parse('2030-01-01')->addMonths($mes), 'valor' => 0.0];
        }
        $fluxo[] = ['data' => Carbon::parse('2031-01-01'), 'valor' => -1.0];

        $tir = $this->calculator->calcularTir($fluxo);

        $this->assertNotNull($tir);
        $this->assertGreaterThan(0.0, $tir);
        $base = Carbon::parse('2030-01-01');
        $npv = 0.0;
        foreach ($fluxo as $item) {
            $anos = $base->diffInDays($item['data']) / 365;
            $npv += $item['valor'] / pow(1 + $tir, $anos);
        }
        $this->assertEqualsWithDelta(0.0, $npv, 0.000001);
    }

    public function test_tir_ambigua_com_multiplas_raizes_positivas_retorna_null(): void
    {
        $fluxo = [
            ['data' => Carbon::parse('2030-01-01'), 'valor' => -100.0],
            ['data' => Carbon::parse('2030-02-01'), 'valor' => 230.0],
            ['data' => Carbon::parse('2030-03-01'), 'valor' => -132.0],
        ];

        $this->assertNull($this->calculator->calcularTir($fluxo));
    }

    public function test_aporte_devolucao_e_distribuicao_reproduzem_a_sequencia_da_planilha(): void
    {
        $fluxo = [
            '2030-01' => [
                'saldo_mes' => -100.0,
                'saldo_acumulado_mes' => -100.0,
                'despesas' => ['total' => 100.0],
            ],
            '2030-02' => [
                'saldo_mes' => 220.0,
                'saldo_acumulado_mes' => 120.0,
                'despesas' => ['total' => 20.0],
            ],
            '2030-03' => [
                'saldo_mes' => 30.0,
                'saldo_acumulado_mes' => 150.0,
                'despesas' => ['total' => 10.0],
            ],
            '2030-04' => [
                'saldo_mes' => 0.0,
                'saldo_acumulado_mes' => 150.0,
                'despesas' => ['total' => 0.0],
            ],
        ];
        [$datas, $params, $dadosProdutos] = $this->makeFinanceScenario(
            inicioObra: '2030-01-01',
            mesesObra: 4,
            distribuicao: 1.0,
        );

        [$fluxoFinanceiro] = $this->calculator->calcularIndicadoresFinanceiros(
            $fluxo,
            $datas,
            $params,
            $dadosProdutos,
        );

        $this->assertSame(100.0, $fluxoFinanceiro['2030-01']['aporte']);
        $this->assertSame(55.0, $fluxoFinanceiro['2030-02']['devolucao_aporte']);
        $this->assertSame(45.0, $fluxoFinanceiro['2030-03']['devolucao_aporte']);
        $this->assertSame(145.0, $fluxoFinanceiro['2030-02']['distribuicao_lucros']);
        $this->assertSame(5.0, $fluxoFinanceiro['2030-04']['distribuicao_lucros']);
        $this->assertSame(0.0, $fluxoFinanceiro['2030-04']['saldo_acumulado']);
    }

    public function test_tir_financeira_usa_saldo_acumulado_do_fcfe_e_nao_a_politica_de_distribuicao(): void
    {
        $fluxo = [
            '2030-01' => ['saldo_mes' => -100.0, 'saldo_acumulado_mes' => -100.0],
            '2030-02' => ['saldo_mes' => 220.0, 'saldo_acumulado_mes' => 120.0],
        ];
        [$datas, $paramsSemDistribuicao, $dadosProdutos] = $this->makeFinanceScenario(
            inicioObra: '2030-01-01',
            mesesObra: 2,
            distribuicao: 0.0,
        );
        [, $paramsComDistribuicao] = $this->makeFinanceScenario(
            inicioObra: '2030-01-01',
            mesesObra: 2,
            distribuicao: 1.0,
        );

        [$fluxoSemDistribuicao, $indicadoresSemDistribuicao] = $this->calculator->calcularIndicadoresFinanceiros(
            $fluxo,
            $datas,
            $paramsSemDistribuicao,
            $dadosProdutos,
        );
        [$fluxoComDistribuicao, $indicadoresComDistribuicao] = $this->calculator->calcularIndicadoresFinanceiros(
            $fluxo,
            $datas,
            $paramsComDistribuicao,
            $dadosProdutos,
        );

        // A planilha calcula XIRR sobre os saldos acumulados: [-100, +120].
        $tirEsperada = pow(1.2, 365 / 31) - 1;
        $this->assertEqualsWithDelta($tirEsperada, $indicadoresSemDistribuicao['tir_financeira'], 0.000001);
        $this->assertEqualsWithDelta($tirEsperada, $indicadoresComDistribuicao['tir_financeira'], 0.000001);
        $this->assertSame(-100.0, $fluxoComDistribuicao['2030-01']['fluxo_livre_equity']);
        $this->assertSame(220.0, $fluxoComDistribuicao['2030-02']['fluxo_livre_equity']);
        $this->assertSame(2, $indicadoresComDistribuicao['payback_financeiro_meses']);
        $this->assertSame(
            $indicadoresSemDistribuicao['tir_financeira'],
            $indicadoresComDistribuicao['tir_financeira'],
        );
        $this->assertNotSame(
            $fluxoSemDistribuicao['2030-02']['saldo_acumulado'],
            $fluxoComDistribuicao['2030-02']['saldo_acumulado'],
        );
    }

    public function test_cronograma_divida_pj_zera_saldo_final(): void
    {
        $cronograma = $this->impostos->gerarCronogramaDividaPj(
            valorObra: 1_000_000.0,
            mesesObra: 12,
            taxaAnual: 0.105,
            percentualAntecipado: 0.10,
            valorBaseAdicional: 0.0,
            carenciaMeses: 2,
            amortizacaoParcelas: 6,
            inicioObra: Carbon::parse('2026-01-01'),
            dataEntrega: Carbon::parse('2027-01-01'),
        );

        $this->assertEqualsWithDelta(100_000.0, $cronograma['valor_antecipado'], 0.01);
        $this->assertEqualsWithDelta(0.0, $cronograma['saldo_final'], 0.01);
        $this->assertEqualsWithDelta(
            $cronograma['valor_antecipado'],
            $cronograma['principal_amortizado'],
            0.02
        );
        $this->assertNotEmpty($cronograma['por_mes']);
    }

    public function test_plano_empresario_libera_divida_pj_conforme_medicoes_da_obra(): void
    {
        $cronograma = $this->impostos->gerarCronogramaDividaPjPorMedicao(
            valorObra: 1_000_000.0,
            taxaAnual: 0.12,
            percentualFinanciado: 0.80,
            carenciaMeses: 1,
            amortizacaoParcelas: 2,
            inicioObra: Carbon::parse('2026-01-01'),
            dataEntrega: Carbon::parse('2026-03-01'),
            curvaMedicao: [25.0, 75.0],
        );

        $this->assertEqualsWithDelta(200_000.0, $cronograma['por_mes']['2026-01']['desembolso'], 0.01);
        $this->assertEqualsWithDelta(600_000.0, $cronograma['por_mes']['2026-02']['desembolso'], 0.01);
        $this->assertGreaterThan(
            $cronograma['por_mes']['2026-01']['juros_pagos'],
            $cronograma['por_mes']['2026-02']['juros_pagos'],
        );
        $this->assertEqualsWithDelta(800_000.0, $cronograma['valor_antecipado'], 0.01);
        $this->assertEqualsWithDelta(800_000.0, $cronograma['principal_amortizado'], 0.01);
        $this->assertSame(0.0, $cronograma['saldo_final']);
    }

    public function test_cronograma_pj_separa_desembolso_por_demanda_da_janela_de_obra(): void
    {
        $cronograma = $this->impostos->gerarCronogramaDividaPj(
            valorObra: 186_810_380.0,
            mesesObra: 36,
            taxaAnual: 0.105,
            percentualAntecipado: 0.10,
            valorBaseAdicional: 0.0,
            carenciaMeses: 6,
            amortizacaoParcelas: 18,
            inicioObra: Carbon::parse('2029-12-01'),
            dataEntrega: Carbon::parse('2032-12-01'),
            dataDesembolso: Carbon::parse('2029-09-01'),
        );

        $this->assertEqualsWithDelta(18_681_038.0, $cronograma['por_mes']['2029-09']['desembolso'], 0.01);
        $this->assertGreaterThan(0.0, $cronograma['por_mes']['2029-09']['juros_pagos']);
        $this->assertArrayNotHasKey('2029-10', $cronograma['por_mes']);
        $this->assertArrayNotHasKey('2029-11', $cronograma['por_mes']);
        $this->assertGreaterThan(0.0, $cronograma['por_mes']['2029-12']['juros_pagos']);
        $this->assertGreaterThan(0.0, $cronograma['por_mes']['2033-06']['amortizacao']);
        $this->assertSame(0.0, $cronograma['por_mes']['2034-11']['saldo_final']);
        $this->assertEqualsWithDelta(8_038_273.51, $cronograma['juros_totais'], 0.02);
    }

    public function test_divida_pj_usa_mesma_base_completa_de_obra_da_dre(): void
    {
        $inicioObra = Carbon::parse('2030-01-01');
        $fimObra = $inicioObra->copy()->addMonths(35);
        $dataEntrega = $fimObra->copy()->addMonth();
        $fluxo = [
            '2030-01' => ['saldo_mes' => -1_000.0, 'saldo_acumulado_mes' => -1_000.0],
            '2030-02' => ['saldo_mes' => 2_000.0, 'saldo_acumulado_mes' => 1_000.0],
        ];
        $datas = [
            'inicioObra' => $inicioObra,
            'fimObra' => $fimObra,
            'dataEntrega' => $dataEntrega,
            'inicioPos' => $dataEntrega,
            'fimPos' => $dataEntrega->copy()->addMonths(59),
        ];
        $params = [
            'mesesObra' => 36,
            'mesesPosObra' => 60,
            'taxaJurosPj' => 0.105,
            'percentualAntecipacaoPj' => 0.10,
            'carenciaPjMeses' => 6,
            'amortizacaoPjParcelas' => 18,
            'taxaExposicaoAplicada' => 0.0,
            'aporteAdicionalMensal' => 0.0,
            'devolucaoAportePercentual' => 0.0,
            'distribuicaoLucrosPercentualObra' => 0.0,
            'compraTerreno' => 0.0,
            'percentualContrapartidas' => 0.01,
            'custoAreaComum' => 0.0,
            'canteiroMensal' => 85_000.0,
        ];
        $dadosProdutos = [
            'vgv' => 445_000_000.0,
            'totalUnidades' => 2_000,
            'permutas' => 0,
            'custoObraHabitacao' => 132_610_380.0,
            'custoInfraestrutura' => 42_240_000.0,
            'custoNaoIncidente' => 4_450_000.0,
            'produtos' => [['preco' => 200_000.0]],
        ];

        [, $indicadores] = $this->calculator->calcularIndicadoresFinanceiros(
            $fluxo,
            $datas,
            $params,
            $dadosProdutos,
        );

        $this->assertEqualsWithDelta(18_681_038.0, $indicadores['divida_pj']['valor_antecipado'], 0.01);
    }

    /**
     * @return array{
     *   0: array<string, Carbon>,
     *   1: array<string, float|int>,
     *   2: array<string, mixed>
     * }
     */
    private function makeFinanceScenario(string $inicioObra, int $mesesObra, float $distribuicao): array
    {
        $inicio = Carbon::parse($inicioObra)->startOfMonth();
        $fimObra = $inicio->copy()->addMonths($mesesObra - 1);
        $dataEntrega = $fimObra->copy()->addMonth();
        $inicioPos = $dataEntrega->copy()->addMonth();

        return [
            [
                'inicioObra' => $inicio,
                'fimObra' => $fimObra,
                'dataEntrega' => $dataEntrega,
                'inicioPos' => $inicioPos,
                'fimPos' => $inicioPos,
            ],
            [
                'mesesObra' => $mesesObra,
                'mesesPosObra' => 1,
                'taxaJurosPj' => 0.0,
                'percentualAntecipacaoPj' => 0.0,
                'carenciaPjMeses' => 0,
                'amortizacaoPjParcelas' => 0,
                'taxaExposicaoAplicada' => 0.0,
                'aporteAdicionalMensal' => 0.0,
                'devolucaoAportePercentual' => 0.0,
                'distribuicaoLucrosPercentualObra' => $distribuicao,
                'compraTerreno' => 0.0,
                'percentualContrapartidas' => 0.0,
                'custoAreaComum' => 0.0,
                'canteiroMensal' => 0.0,
            ],
            [
                'vgv' => 0.0,
                'totalUnidades' => 0,
                'permutas' => 0,
                'custoObraHabitacao' => 0.0,
                'custoInfraestrutura' => 0.0,
                'custoNaoIncidente' => 0.0,
                'produtos' => [['preco' => 0.0]],
            ],
        ];
    }
}
