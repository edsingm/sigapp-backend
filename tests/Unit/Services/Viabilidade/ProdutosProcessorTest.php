<?php

namespace Tests\Unit\Services\Viabilidade;

use App\Services\Tenant\Viabilidade\v1\Calculos\ProdutosProcessor;
use App\Services\Tenant\Viabilidade\v1\ImpostosService;
use Tests\TestCase;

class ProdutosProcessorTest extends TestCase
{
    public function test_mesclar_parametros_nao_sobrescreve_campos_globais_com_valores_do_produto(): void
    {
        $processor = new ProdutosProcessor(new ImpostosService);

        $params = [
            'gastosMensaisStand' => 0.0001,
            'comissaoHousePercentual' => 0.03,
            'percentualVendasHouse' => 0.50,
            'pagamentoComissaoVenda' => 0.50,
            'marketingLancamento' => 0.25,
            'marketingInicioAntesLancamento' => 3,
            'incorporacaoRi' => 0.30,
            'incorporacaoEntrega' => 0.15,
            'incorporacaoAteLancamento' => 0.80,
            'obraAteLancamento' => 0.01,
            'assistenciaTecnicaCurva' => [50.0, 20.0, 10.0, 10.0, 10.0],
        ];

        $dadosProdutos = [
            'produtos' => [
                [
                    'quantidade_unidades' => 100,
                    'gastos_mensais_stand' => 0.90,
                    'comissao_house' => 0.12,
                    'porcentagem_comissao_house' => 0.80,
                    'pagto_comissao_venda' => 0.95,
                    'marketing_lancamento' => 0.60,
                    'marketing_antes_lancamento' => 8,
                    'incorp_ri' => 0.55,
                    'incorp_entrega' => 0.20,
                    'incorp_ate_lancamento' => 0.92,
                    'obra_ateLancamento' => 0.33,
                    'assist_tecnica_curva' => [60.0, 15.0, 10.0, 10.0, 5.0],
                ],
            ],
        ];

        $resultado = $processor->mesclarParametros($params, $dadosProdutos);

        $this->assertSame($params['gastosMensaisStand'], $resultado['gastosMensaisStand']);
        $this->assertSame($params['comissaoHousePercentual'], $resultado['comissaoHousePercentual']);
        $this->assertSame($params['percentualVendasHouse'], $resultado['percentualVendasHouse']);
        $this->assertSame($params['pagamentoComissaoVenda'], $resultado['pagamentoComissaoVenda']);
        $this->assertSame($params['marketingLancamento'], $resultado['marketingLancamento']);
        $this->assertSame($params['marketingInicioAntesLancamento'], $resultado['marketingInicioAntesLancamento']);
        $this->assertSame($params['incorporacaoRi'], $resultado['incorporacaoRi']);
        $this->assertSame($params['incorporacaoEntrega'], $resultado['incorporacaoEntrega']);
        $this->assertSame($params['incorporacaoAteLancamento'], $resultado['incorporacaoAteLancamento']);
        $this->assertSame($params['obraAteLancamento'], $resultado['obraAteLancamento']);
    }

    public function test_mesclar_parametros_mantem_curva_de_assistencia_tecnica_por_produto(): void
    {
        $processor = new ProdutosProcessor(new ImpostosService);

        $params = [
            'assistenciaTecnicaCurva' => [50.0, 20.0, 10.0, 10.0, 10.0],
        ];

        $dadosProdutos = [
            'produtos' => [
                [
                    'quantidade_unidades' => 100,
                    'assist_tecnica_curva' => [45.0, 25.0, 15.0, 10.0, 5.0],
                ],
            ],
        ];

        $resultado = $processor->mesclarParametros($params, $dadosProdutos);

        $this->assertSame([45.0, 25.0, 15.0, 10.0, 5.0], $resultado['assistenciaTecnicaCurva']);
    }
}
