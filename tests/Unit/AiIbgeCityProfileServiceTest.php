<?php

namespace Tests\Unit;

use App\Services\Ai\Tools\AiIbgeCityProfileService;
use App\Services\Ai\Tools\GetCityIbgeProfileTool;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class AiIbgeCityProfileServiceTest extends TestCase
{
    public function test_monta_perfil_de_cidade_com_panorama_historico_e_indicadores(): void
    {
        $this->fakeIbgeHappyPath();

        $service = app(AiIbgeCityProfileService::class);
        $perfil = $service->getCityProfile('3529005');

        $this->assertSame(3529005, $perfil['municipio']['codigo_ibge']);
        $this->assertSame('Marília', $perfil['municipio']['nome']);
        $this->assertSame('mariliense', $perfil['historico']['gentilico']);
        $this->assertSame('95812', $perfil['trabalho_e_renda']['pessoal_ocupado']['valor']);
        $this->assertSame('78500', $perfil['habitacao']['domicilios_recenseados']['valor']);
        $this->assertCount(2, $perfil['panorama']['indicadores_rapidos']);
        $this->assertNull($perfil['trabalho_e_renda']['renda_per_capita_domiciliar']);
        $this->assertSame([], $perfil['avisos']);
    }

    public function test_panorama_html_403_nao_derruba_perfil(): void
    {
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/municipios/4113700' => Http::response([
                'id' => 4113700,
                'nome' => 'Londrina',
                'microrregiao' => [
                    'nome' => 'Londrina',
                    'mesorregiao' => [
                        'nome' => 'Norte Central Paranaense',
                        'UF' => [
                            'sigla' => 'PR',
                            'nome' => 'Paraná',
                            'regiao' => ['nome' => 'Sul'],
                        ],
                    ],
                ],
                'regiao-imediata' => [
                    'nome' => 'Londrina',
                    'regiao-intermediaria' => ['nome' => 'Londrina'],
                ],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/biblioteca*' => Http::response([
                '4113700' => [
                    'HISTORICO' => 'Resumo',
                    'GENTILICO' => 'londrinense',
                ],
            ]),
            // Cloudflare challenge — causa real em produção
            'https://www.ibge.gov.br/cidades-e-estados/*' => Http::response(
                '<html><title>Just a moment...</title></html>',
                403
            ),
            // Payload real do /resultados (sem nome do indicador)
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/*/periodos/*/indicadores/*/resultados/*' => Http::sequence()
                ->push([['id' => 29171, 'res' => [['localidade' => '411370', 'res' => ['2025' => '581382']]]]])
                ->push([['id' => 47001, 'res' => [['localidade' => '411370', 'res' => ['2023' => '50362.05']]]]])
                ->push([['id' => 46997, 'res' => [['localidade' => '411370', 'res' => ['2023' => '1000']]]]])
                ->push([['id' => 143514, 'res' => [['localidade' => '411370', 'res' => ['2023' => '242760']]]]])
                ->push([['id' => 143536, 'res' => [['localidade' => '411370', 'res' => ['2023' => '200000']]]]])
                ->push([['id' => 143558, 'res' => [['localidade' => '411370', 'res' => ['2023' => '2.8']]]]])
                ->push([['id' => 27664, 'res' => [['localidade' => '411370', 'res' => ['2010' => '100']]]]])
                ->push([['id' => 27658, 'res' => [['localidade' => '411370', 'res' => ['2010' => '90']]]]])
                ->push([['id' => 27744, 'res' => [['localidade' => '411370', 'res' => ['2010' => '3.1']]]]])
                ->push([['id' => 28844, 'res' => [['localidade' => '411370', 'res' => ['2010' => '50']]]]])
                ->push([]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/*' => Http::response([]),
        ]);

        $perfil = app(AiIbgeCityProfileService::class)->getCityProfile('4113700');

        $this->assertSame('Londrina', $perfil['municipio']['nome']);
        $this->assertSame([], $perfil['panorama']['indicadores_rapidos']);
        $this->assertSame('581382', $perfil['panorama']['destaques']['populacao_estimada']['valor']);
        $this->assertSame('População estimada', $perfil['panorama']['destaques']['populacao_estimada']['indicador']);
        $this->assertSame('londrinense', $perfil['historico']['gentilico']);
    }

    public function test_resolve_por_cidade_e_uf_e_alias_estado_na_tool(): void
    {
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados/PR/municipios' => Http::response([
                ['id' => 4113700, 'nome' => 'Londrina', 'microrregiao' => [
                    'nome' => 'Londrina',
                    'mesorregiao' => [
                        'nome' => 'Norte Central Paranaense',
                        'UF' => ['sigla' => 'PR', 'nome' => 'Paraná', 'regiao' => ['nome' => 'Sul']],
                    ],
                ], 'regiao-imediata' => ['nome' => 'Londrina', 'regiao-intermediaria' => ['nome' => 'Londrina']]],
                ['id' => 4106902, 'nome' => 'Curitiba', 'microrregiao' => [
                    'nome' => 'Curitiba',
                    'mesorregiao' => [
                        'nome' => 'Metropolitana de Curitiba',
                        'UF' => ['sigla' => 'PR', 'nome' => 'Paraná', 'regiao' => ['nome' => 'Sul']],
                    ],
                ], 'regiao-imediata' => ['nome' => 'Curitiba', 'regiao-intermediaria' => ['nome' => 'Curitiba']]],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/biblioteca*' => Http::response(['4113700' => ['GENTILICO' => 'londrinense']]),
            'https://www.ibge.gov.br/cidades-e-estados/*' => Http::response('', 403),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/*/periodos/*/indicadores/*/resultados/*' => Http::response([
                ['id' => 29171, 'res' => [['localidade' => '411370', 'res' => ['2025' => '581382']]]],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/*' => Http::response([]),
        ]);

        $tool = new GetCityIbgeProfileTool(app(AiIbgeCityProfileService::class));
        // LLM costuma mandar "estado" em vez de "uf"
        $out = json_decode((string) $tool->handle(new Request([
            'cidade' => 'Londrina',
            'estado' => 'PR',
        ])), true);

        $this->assertTrue($out['ok'] ?? false);
        $this->assertSame('Londrina', $out['data']['municipio']['nome'] ?? null);
        $this->assertSame(4113700, $out['data']['municipio']['codigo_ibge'] ?? null);
    }

    public function test_parse_cidade_com_uf_embutida(): void
    {
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados/PR/municipios' => Http::response([
                ['id' => 4113700, 'nome' => 'Londrina', 'microrregiao' => [
                    'nome' => 'Londrina',
                    'mesorregiao' => [
                        'nome' => 'Norte',
                        'UF' => ['sigla' => 'PR', 'nome' => 'Paraná', 'regiao' => ['nome' => 'Sul']],
                    ],
                ], 'regiao-imediata' => ['nome' => 'Londrina', 'regiao-intermediaria' => ['nome' => 'Londrina']]],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/biblioteca*' => Http::response(['4113700' => []]),
            'https://www.ibge.gov.br/cidades-e-estados/*' => Http::response('', 403),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/*/periodos/*/indicadores/*/resultados/*' => Http::response([
                ['id' => 1, 'res' => [['res' => ['2025' => '1']]]],
            ]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/*' => Http::response([]),
        ]);

        $tool = new GetCityIbgeProfileTool(app(AiIbgeCityProfileService::class));
        $out = json_decode((string) $tool->handle(new Request([
            'cidade' => 'Londrina - PR',
        ])), true);

        $this->assertTrue($out['ok'] ?? false, json_encode($out));
        $this->assertSame(4113700, $out['data']['municipio']['codigo_ibge'] ?? null);
    }

    private function fakeIbgeHappyPath(): void
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
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/*/periodos/*/indicadores/*/resultados/*' => Http::sequence()
                ->push([['indicador' => 'População estimada', 'unidade' => ['id' => 'pessoas'], 'fonte' => [['fontes' => ['IBGE']]], 'res' => [['res' => ['2025' => '247348']]]]])
                ->push([['indicador' => 'Série revisada', 'unidade' => ['id' => 'R$'], 'fonte' => [['fontes' => ['IBGE']]], 'res' => [['res' => ['2023' => '52635.31']]]]])
                ->push([['indicador' => 'Série revisada', 'unidade' => ['id' => 'R$ 1.000'], 'fonte' => [['fontes' => ['IBGE']]], 'res' => [['res' => ['2023' => '13018912.55']]]]])
                ->push([['indicador' => 'Pessoal ocupado', 'unidade' => ['id' => 'pessoas'], 'fonte' => [['fontes' => ['IBGE']]], 'res' => [['res' => ['2023' => '95812']]]]])
                ->push([['indicador' => 'Pessoal ocupado assalariado', 'unidade' => ['id' => 'pessoas'], 'fonte' => [['fontes' => ['IBGE']]], 'res' => [['res' => ['2023' => '74527']]]]])
                ->push([['indicador' => 'Salário médio mensal', 'unidade' => ['id' => 'salários mínimos'], 'fonte' => [['fontes' => ['IBGE']]], 'res' => [['res' => ['2023' => '2.6']]]]])
                ->push([['indicador' => 'Recenseados', 'unidade' => ['id' => 'domicílios'], 'fonte' => [['fontes' => ['IBGE']]], 'res' => [['res' => ['2010' => '78500']]]]])
                ->push([['indicador' => 'Ocupados', 'unidade' => ['id' => 'domicílios'], 'fonte' => [['fontes' => ['IBGE']]], 'res' => [['res' => ['2010' => '68764']]]]])
                ->push([['indicador' => 'Média de moradores em domicílios particulares ocupados', 'unidade' => ['id' => 'moradores'], 'fonte' => [['fontes' => ['IBGE']]], 'res' => [['res' => ['2010' => '3.12']]]]])
                ->push([['indicador' => 'Com acesso à internet', 'unidade' => ['id' => 'domicílios'], 'fonte' => [['fontes' => ['IBGE']]], 'res' => [['res' => ['2010' => '30449']]]]])
                ->push([]),
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/*' => Http::response([]),
        ]);
    }
}
