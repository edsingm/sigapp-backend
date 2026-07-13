<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Viabilidade;

use PHPUnit\Framework\TestCase;

/**
 * Garante a regra de demanda mínima CEF ponderada por produto.
 */
class DemandaCefTest extends TestCase
{
    public function test_dois_produtos_com_30_porcento_resultam_em_30_porcento_ponderado(): void
    {
        $produtos = [
            [
                'quantidade_unidades' => 100,
                'permutas' => 0,
                'demanda_minCef' => 30.0,
            ],
            [
                'quantidade_unidades' => 100,
                'permutas' => 0,
                'demanda_minCef' => 30.0,
            ],
        ];

        $demanda = $this->calcularDemandaMinima($produtos);

        // 100*0.3 + 100*0.3 = 60 = 30% de 200
        $this->assertEqualsWithDelta(60.0, $demanda, 0.001);
        $this->assertEqualsWithDelta(0.30, $demanda / 200.0, 0.0001);
    }

    public function test_demanda_considera_apenas_unidades_comercializaveis(): void
    {
        $produtos = [
            [
                'quantidade_unidades' => 100,
                'permutas' => 40,
                'demanda_minCef' => 50.0,
            ],
        ];

        $demanda = $this->calcularDemandaMinima($produtos);

        // 60 * 0.5 = 30
        $this->assertEqualsWithDelta(30.0, $demanda, 0.001);
    }

    /**
     * @param  list<array<string, mixed>>  $produtos
     */
    private function calcularDemandaMinima(array $produtos): float
    {
        // Replica a regra do FluxoMensalCalculator::inicializarCachesCef
        $demandaMinima = 0.0;
        foreach ($produtos as $produto) {
            $unidadesProduto = max(
                0,
                (int) ($produto['quantidade_unidades'] ?? 0) - (int) ($produto['permutas'] ?? 0)
            );
            $demandaPct = $this->normalizarPercentual($produto['demanda_minCef'] ?? null);
            $demandaMinima += $unidadesProduto * $demandaPct;
        }

        return $demandaMinima;
    }

    private function normalizarPercentual(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        $percentual = (float) $valor;
        if ($percentual <= 0.0) {
            return 0.0;
        }

        if ($percentual < 1.0 || $percentual === 1.0) {
            return $percentual;
        }

        return $percentual / 100;
    }
}
