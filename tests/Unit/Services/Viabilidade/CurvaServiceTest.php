<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Viabilidade;

use App\Services\Tenant\Viabilidade\v1\CurvaService;
use PHPUnit\Framework\TestCase;

class CurvaServiceTest extends TestCase
{
    private CurvaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CurvaService;
    }

    public function test_curva_oficial_18_bate_com_aux_obras(): void
    {
        $curva = $this->service->getCurvaObraParaPrazo(18);

        $this->assertCount(18, $curva);
        $this->assertEqualsWithDelta(100.0, array_sum($curva), 0.01);
        $this->assertEqualsWithDelta(0.75, $curva[0], 0.001);
        $this->assertEqualsWithDelta(11.0, $curva[9], 0.001);
    }

    public function test_prazo_48_nao_reutiliza_vetor_de_36(): void
    {
        $curva = $this->service->getCurvaObraParaPrazo(48);
        $warnings = $this->service->pullWarnings();

        $this->assertCount(48, $curva);
        $this->assertEqualsWithDelta(100.0, array_sum($curva), 0.01);
        $this->assertNotEmpty($warnings);

        $acumulado = 0.0;
        $prev = -1.0;
        foreach ($curva as $percentual) {
            $acumulado += $percentual;
            $this->assertGreaterThanOrEqual($prev - 1e-9, $acumulado);
            $prev = $acumulado;
        }
        $this->assertEqualsWithDelta(100.0, $acumulado, 0.01);
    }

    public function test_curva_financeira_medicao_com_5_porcento_finais(): void
    {
        $curva = $this->service->getCurvaFinanceiraMedicaoParaPrazo(36);

        $this->assertEqualsWithDelta(100.0, array_sum($curva), 0.05);
        $this->assertEqualsWithDelta(2.0, $curva[36 + 1], 0.01);
        $this->assertEqualsWithDelta(3.0, $curva[36 + 5], 0.01);

        $duranteObra = 0.0;
        for ($i = 0; $i < 36; $i++) {
            $duranteObra += $curva[$i] ?? 0.0;
        }
        $this->assertEqualsWithDelta(95.0, $duranteObra, 0.1);
    }
}
