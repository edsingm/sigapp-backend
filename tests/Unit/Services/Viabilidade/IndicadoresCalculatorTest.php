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
}
