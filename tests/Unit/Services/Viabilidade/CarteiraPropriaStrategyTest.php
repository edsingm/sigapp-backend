<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Viabilidade;

use App\Services\Tenant\Viabilidade\CarteiraPropriaStrategy;
use PHPUnit\Framework\TestCase;

class CarteiraPropriaStrategyTest extends TestCase
{
    public function test_calcula_entrada_e_parcelas_com_incc_antes_da_entrega(): void
    {
        $result = (new CarteiraPropriaStrategy)->calculate([
            'quantidade_lotes' => 1,
            'preco_lote' => 220000,
            'mes_lancamento' => 0,
            'mes_entrega' => 30,
            'prazo_parcelas_meses' => 24,
            'curva_vendas_percentual' => [1.0],
        ]);

        $this->assertSame('carteira_propria', $result['motor']);
        $this->assertEquals(44000.0, $result['fluxo_mensal']['mes_000']['receitas']['total']);
        $this->assertEqualsWithDelta(8283.733333, $result['fluxo_mensal']['mes_001']['receitas']['total'], 0.0001);
    }
}
