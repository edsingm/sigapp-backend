<?php

namespace Tests\Unit;

use App\Services\AiIbgeCityProfileService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiIbgeCityProfileServiceTest extends TestCase
{
    public function test_monta_perfil_de_cidade_com_panorama_historico_e_indicadores(): void
    {
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/municipios/3529005' => Http::response([
                'id' => 3529005,
                'nome' => 'Marília',
                'microrregiao' => [
                    'nome' => 'Marília',
                    'mesorregiao' => [
                        'nome' => 'Marília',
                        'UF' => [
                            'sigla' => 'SP',
                            'nome' => 'São Paulo',
                            'regiao' => [
                                'nome' => 'Sudeste',
                            ],
                        ],
                    ],
                ],
                'regiao-imediata' => [
                    'nome' => 'Marília',
                    'regiao-intermediaria' => [
                        'nome' => 'Marília',
                    ],
                ],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/biblioteca*' => Http::response([
                '3529005' => [
                    'HISTORICO' => 'Resumo histórico',
                    'FORMACAO_ADMINISTRATIVA' => 'Formação administrativa',
                    'GENTILICO' => 'mariliense',
                    'HISTORICO_FONTE' => 'Prefeitura de Marília',
                ],
            ]),
            'https://www.ibge.gov.br/cidades-e-estados/sp/marilia.html' => Http::response(
                <<<'HTML'
                <html><body>
                <ul class="resultados-padrao">
                    <li data-grafico="47001">
                        <div class="indicador">
                            <div class="ind-label"><p>PIB per capita</p></div>
                            <p class="ind-value">52.635,31 <span class="indicador-unidade">R$</span><small>[2023]</small></p>
                        </div>
                    </li>
                    <li>
                        <div class="indicador">
                            <div class="ind-label"><p>População estimada</p></div>
                            <p class="ind-value">247.348 <span class="indicador-unidade">pessoas</span><small>[2025]</small></p>
                        </div>
                    </li>
                </ul>
                </body></html>
                HTML
            ),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/33/periodos/2025/indicadores/29171*' => Http::response([
                [
                    'indicador' => 'População estimada',
                    'unidade' => ['id' => 'pessoas'],
                    'fonte' => [['fontes' => ['IBGE']]],
                    'res' => [['res' => ['2025' => '247348']]],
                ],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/38/periodos/2023/indicadores/47001*' => Http::response([
                [
                    'indicador' => 'Série revisada',
                    'unidade' => ['id' => 'R$'],
                    'fonte' => [['fontes' => ['IBGE']]],
                    'res' => [['res' => ['2023' => '52635.31']]],
                ],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/38/periodos/2023/indicadores/46997*' => Http::response([
                [
                    'indicador' => 'Série revisada',
                    'unidade' => ['id' => 'R$ 1.000'],
                    'fonte' => [['fontes' => ['IBGE']]],
                    'res' => [['res' => ['2023' => '13018912.55']]],
                ],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/19/periodos/2023/indicadores/143514*' => Http::response([
                [
                    'indicador' => 'Pessoal ocupado',
                    'unidade' => ['id' => 'pessoas'],
                    'fonte' => [['fontes' => ['IBGE']]],
                    'res' => [['res' => ['2023' => '95812']]],
                ],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/19/periodos/2023/indicadores/143536*' => Http::response([
                [
                    'indicador' => 'Pessoal ocupado assalariado',
                    'unidade' => ['id' => 'pessoas'],
                    'fonte' => [['fontes' => ['IBGE']]],
                    'res' => [['res' => ['2023' => '74527']]],
                ],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/19/periodos/2023/indicadores/143558*' => Http::response([
                [
                    'indicador' => 'Salário médio mensal',
                    'unidade' => ['id' => 'salários mínimos'],
                    'fonte' => [['fontes' => ['IBGE']]],
                    'res' => [['res' => ['2023' => '2.6']]],
                ],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/23/periodos/2010/indicadores/27664*' => Http::response([
                [
                    'indicador' => 'Recenseados',
                    'unidade' => ['id' => 'domicílios'],
                    'fonte' => [['fontes' => ['IBGE']]],
                    'res' => [['res' => ['2010' => '78500']]],
                ],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/23/periodos/2010/indicadores/27658*' => Http::response([
                [
                    'indicador' => 'Ocupados',
                    'unidade' => ['id' => 'domicílios'],
                    'fonte' => [['fontes' => ['IBGE']]],
                    'res' => [['res' => ['2010' => '68764']]],
                ],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/23/periodos/2010/indicadores/27744*' => Http::response([
                [
                    'indicador' => 'Média de moradores em domicílios particulares ocupados',
                    'unidade' => ['id' => 'moradores'],
                    'fonte' => [['fontes' => ['IBGE']]],
                    'res' => [['res' => ['2010' => '3.12']]],
                ],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/23/periodos/2010/indicadores/28844*' => Http::response([
                [
                    'indicador' => 'Com acesso à internet',
                    'unidade' => ['id' => 'domicílios'],
                    'fonte' => [['fontes' => ['IBGE']]],
                    'res' => [['res' => ['2010' => '30449']]],
                ],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/45/periodos/2025/indicadores/288790*' => Http::response([]),
        ]);

        $service = app(AiIbgeCityProfileService::class);
        $perfil = $service->getCityProfile('3529005');

        $this->assertSame(3529005, $perfil['municipio']['codigo_ibge']);
        $this->assertSame('Marília', $perfil['municipio']['nome']);
        $this->assertSame('mariliense', $perfil['historico']['gentilico']);
        $this->assertSame('95812', $perfil['trabalho_e_renda']['pessoal_ocupado']['valor']);
        $this->assertSame('78500', $perfil['habitacao']['domicilios_recenseados']['valor']);
        $this->assertCount(2, $perfil['panorama']['indicadores_rapidos']);
        $this->assertNull($perfil['trabalho_e_renda']['renda_per_capita_domiciliar']);
    }
}
