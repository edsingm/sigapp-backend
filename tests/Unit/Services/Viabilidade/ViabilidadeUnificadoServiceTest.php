<?php

namespace Tests\Unit\Services\Viabilidade;

use App\Services\Tenant\Viabilidade\v1\CurvaService;
use App\Services\Tenant\Viabilidade\v1\ImpostosService;
use App\Services\Tenant\Viabilidade\v1\PremissasViabilidadeService;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeUnificadoService;
use App\Services\Tenant\Viabilidade\v1\Calculos\DespesasCalculator;
use App\Services\Tenant\Viabilidade\v1\Calculos\DreCalculator;
use App\Services\Tenant\Viabilidade\v1\Calculos\FluxoMensalCalculator;
use App\Services\Tenant\Viabilidade\v1\Calculos\IndicadoresCalculator;
use App\Services\Tenant\Viabilidade\v1\Calculos\PocCalculator;
use App\Services\Tenant\Viabilidade\v1\Calculos\ProdutosProcessor;
use App\Services\Tenant\Viabilidade\v1\Calculos\ReceitasCalculator;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Testes unitários para ViabilidadeUnificadoService.
 *
 * Os testes cobrem os três métodos públicos de cálculo direto por array,
 * que não dependem de banco de dados:
 *  - calcularDre()
 *  - calcularReceitas()
 *  - calcularDespesas()
 *
 * Pelo design atual o serviço mantém estado interno entre chamadas
 * (caches mutáveis). Os testes de regressão para esse comportamento
 * serão habilitados após a refatoração que extrai o ViabilidadeFluxoContext.
 */
class ViabilidadeUnificadoServiceTest extends TestCase
{
    private ViabilidadeUnificadoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Instância limpa a cada teste — sem DI para evitar estado compartilhado
        $this->service = $this->makeService();
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function makeService(): ViabilidadeUnificadoService
    {
        $curvaService = new CurvaService;
        $impostosService = new ImpostosService;
        $dreCalculator = new DreCalculator($impostosService);
        $receitasCalculator = new ReceitasCalculator($curvaService);
        $despesasCalculator = new DespesasCalculator($curvaService, $dreCalculator);
        $indicadoresCalculator = new IndicadoresCalculator($impostosService);
        $pocCalculator = new PocCalculator;
        $produtosProcessor = new ProdutosProcessor($impostosService);
        $fluxoMensalCalculator = new FluxoMensalCalculator(
            $curvaService,
            $receitasCalculator,
            $despesasCalculator,
            $dreCalculator,
            $indicadoresCalculator,
            $pocCalculator,
            $produtosProcessor,
        );
        $premissasService = new PremissasViabilidadeService;

        return new ViabilidadeUnificadoService(
            $curvaService,
            $impostosService,
            $dreCalculator,
            $receitasCalculator,
            $despesasCalculator,
            $indicadoresCalculator,
            $pocCalculator,
            $produtosProcessor,
            $fluxoMensalCalculator,
            $premissasService,
        );
    }

    /**
     * Retorna parâmetros completos a partir dos valores padrão de config.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function makeParams(array $overrides = []): array
    {
        $d = [
            'pis_cofins' => 4.0, 'iss' => 0.0, 'outros_impostos' => 0.5,
            'comissao' => 0.0, 'parceria_vgv' => 0.0,
            'infra_nao_incidente' => 1.0, 'incorporacao' => 1.0,
            'area_comum' => 0.00, 'contrapartidas' => 0.0,
            'canteiro_mensal' => 85715.0, 'mo_administrativa' => 62502.0,
            'seguros' => 0.5, 'assistencia_tecnica' => 1.0,
            'despesas_comerciais' => 5.0,
            'stand_vendas' => 0.0, 'mobilia_decoracao' => 90000.0,
            'ajuda_custo_gerente' => 5000.0, 'ajuda_custo_gerente_regional' => 2733.0,
            'reembolso_logistica' => 5000.0,
            'bonus_cca' => 350.0, 'bonus_gerente' => 0.3, 'bonus_gerente_regional' => 0.12,
            'bonus_credito' => 0.05, 'bonus_gestor_comercial' => 0.05,
            'pagamento_comissao_desligamento' => 50.0, 'parcelamento_comissao_meses' => 18,
            'marketing' => 1.0,
            'itbi_iptu' => 1.1, 'registro' => 2500.00,
            'contratos_cef' => 300.00, 'produtos_cef' => 0.5,
            'outras_despesas_financeiras' => 0.0,
            'prazo_obra' => 36,
            'taxa_juros_pj' => 10.5, 'percentual_antecipacao_pj' => 10.0,
            'aporte_adicional_mensal' => 0.0, 'devolucao_aporte_percentual' => 20.0,
            'distribuicao_lucros_percentual_obra' => 100.0, 'taxa_exposicao_aplicada' => 12.5,
        ];
        $p = [
            'meses_incorporacao' => 18, 'meses_lancamento' => 6,
            'meses_entrega' => 1, 'meses_pos_obra' => 60,
        ];

        $base = [
            'percentualImpostos' => (($d['pis_cofins']) + ($d['iss']) + ($d['outros_impostos'])) / 100,
            'percentualPisCofins' => $d['pis_cofins'] / 100,
            'percentualIss' => $d['iss'] / 100,
            'percentualOutrosImpostos' => $d['outros_impostos'] / 100,
            'percentualComissao' => $d['comissao'] / 100,
            'parceriaVgv' => $d['parceria_vgv'] / 100,
            'infraNaoIncidente' => $d['infra_nao_incidente'] / 100,
            'percentualIncorporacao' => $d['incorporacao'] / 100,
            'incorporacaoRi' => 0.30,
            'incorporacaoEntrega' => 0.15,
            'incorporacaoAteLancamento' => 0.80,
            'custoAreaComum' => $d['area_comum'],
            'percentualContrapartidas' => $d['contrapartidas'] / 100,
            'canteiroMensal' => $d['canteiro_mensal'],
            'moAdministrativa' => $d['mo_administrativa'],
            'percentualSeguros' => $d['seguros'] / 100,
            'percentualAssistenciaTecnica' => $d['assistencia_tecnica'] / 100,
            'assistenciaTecnicaCurva' => [50, 20, 10, 10, 10],
            'percentualDespesasComerciais' => $d['despesas_comerciais'] / 100,
            'standVendas' => $d['stand_vendas'],
            'mobiliaDecoracao' => $d['mobilia_decoracao'],
            'gastosMensaisStand' => 0.0001,
            'comissaoHousePercentual' => 0.03,
            'comissaoImobiliariasPercentual' => 0.035,
            'percentualVendasHouse' => 0.50,
            'ajudaCustoGerente' => $d['ajuda_custo_gerente'],
            'ajudaCustoGerenteRegional' => $d['ajuda_custo_gerente_regional'],
            'reembolsoLogistica' => $d['reembolso_logistica'],
            'bonusCca' => $d['bonus_cca'],
            'bonusGerente' => $d['bonus_gerente'] / 100,
            'bonusGerenteRegional' => $d['bonus_gerente_regional'] / 100,
            'bonusCredito' => $d['bonus_credito'] / 100,
            'bonusGestorComercial' => $d['bonus_gestor_comercial'] / 100,
            'pagamentoComissaoVenda' => 0.50,
            'pagamentoComissaoDesligamento' => $d['pagamento_comissao_desligamento'] / 100,
            'parcelamentoComissaoMeses' => (int) $d['parcelamento_comissao_meses'],
            'percentualMarketing' => $d['marketing'] / 100,
            'marketingLancamento' => 0.25,
            'marketingInicioAntesLancamento' => 3,
            'custoItbiIptu' => $d['itbi_iptu'] / 100,
            'custoRegistro' => $d['registro'],
            'custoMedicaoContratacao' => 24000.00,
            'custoContratosCef' => $d['contratos_cef'],
            'percentualProdutosCef' => $d['produtos_cef'] / 100,
            'percentualOutrasDespesasFinanceiras' => $d['outras_despesas_financeiras'] / 100,
            'mesesObra' => (int) $d['prazo_obra'],
            'mesesIncorporacao' => (int) $p['meses_incorporacao'],
            'mesesLancamento' => (int) $p['meses_lancamento'],
            'mesesEntrega' => $p['meses_entrega'],
            'mesesPosObra' => $p['meses_pos_obra'],
            'compraTerreno' => 0.0,
            'taxaJurosPj' => $d['taxa_juros_pj'] / 100,
            'percentualAntecipacaoPj' => $d['percentual_antecipacao_pj'] / 100,
            'carenciaPjMeses' => 6,
            'amortizacaoPjParcelas' => 18,
            'aporteAdicionalMensal' => $d['aporte_adicional_mensal'],
            'devolucaoAportePercentual' => $d['devolucao_aporte_percentual'] / 100,
            'distribuicaoLucrosPercentualObra' => $d['distribuicao_lucros_percentual_obra'] / 100,
            'taxaExposicaoAplicada' => $d['taxa_exposicao_aplicada'] / 100,
        ];

        return array_merge($base, $overrides);
    }

    /**
     * Retorna dados de produtos simplificados para uso nos testes.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function makeDadosProdutos(array $overrides = []): array
    {
        $vgv = 10_000_000.0;

        $base = [
            'vgv' => $vgv,
            'vgvSemValorTerrenista' => $vgv,
            'vgvSemUnidPermutas' => $vgv,
            'vgvComCorrecao' => $vgv,
            'correcaoSobreVgv' => 0,
            'totalUnidades' => 100,
            'totalUnidadesConstrutora' => 100,
            'permutas' => 0,
            'custoObraHabitacao' => 3_000_000.0,
            'custoInfraestrutura' => 500_000.0,
            'custoNaoIncidente' => 100_000.0,
            'areaConstruida' => 5_000.0,
            'custoCasaM2' => 30_000.0,
            'custoInfraM2' => 500.0,
            'imposto_pis' => 0.0,
            'imposto_cofins' => 0.0,
            'imposto_iss' => 0.0,
            'irrpj' => 0.0,
            'csll' => 0.0,
            'produtos' => [
                [
                    'id' => 1,
                    'terreno_produto_id' => 1,
                    'nome' => 'Apto 2 Dorm',
                    'preco' => 100_000.0,
                    'metragem' => 50.0,
                    'quantidade_unidades' => 100,
                    'custo_m2' => 600.0,
                    'custo_infraestrutura' => 500.0,
                    'vgv_produto' => $vgv,
                    'avaliacao_lotesCef' => 2_000.0,
                    'permutas' => 0,
                    'pgto_por_lote' => 0.0,
                    'demanda_minCef' => 30,
                    'curva_vendas' => [],
                    'imposto_tributos' => 0.045,
                    'imposto_outros' => 0.005,
                    'financeiro' => [
                        'sinal' => 2,
                        'parcela_obra' => 9,
                        'parcela_posChave' => 9,
                        'qtde_parcelas_posChave' => 36,
                        'juros_mensalSinal' => 0,
                        'juros_mensalObra' => 0,
                        'juros_mensalPosChave' => 1,
                        'correcao_anualSinal' => 0,
                        'correcao_anualObra' => 5,
                        'correcao_anualPosChave' => 4.5,
                        'imposto_pis' => 0.0,
                        'imposto_cofins' => 0.0,
                        'imposto_iss' => 0.0,
                        'outras_deducoes' => 0.0,
                        'irrpj' => 0.0,
                        'csll' => 0.0,
                    ],
                ],
            ],
        ];

        return array_merge($base, $overrides);
    }

    /**
     * Retorna datas de períodos posicionadas no futuro para evitar
     * colisões com Carbon::now() internamente.
     *
     * @return array<string, Carbon>
     */
    private function makeDatas(): array
    {
        $lancamento = Carbon::create(2028, 1, 1);
        $incorporacao = $lancamento->copy()->subMonths(18);
        $fimLancamento = $lancamento->copy()->addMonths(5);
        $inicioObra = $fimLancamento->copy()->addMonth();
        $fimObra = $inicioObra->copy()->addMonths(35);
        $entrega = $fimObra->copy()->addMonth();
        $inicioPos = $entrega->copy()->addMonth();
        $fimPos = $inicioPos->copy()->addMonths(59);

        return [
            'inicioIncorporacao' => $incorporacao,
            'dataLancamento' => $lancamento,
            'fimLancamento' => $fimLancamento,
            'inicioObra' => $inicioObra,
            'fimObra' => $fimObra,
            'dataEntrega' => $entrega,
            'inicioPos' => $inicioPos,
            'fimPos' => $fimPos,
        ];
    }

    // =========================================================================
    // calcularDre — Estrutura do retorno
    // =========================================================================

    public function test_calcular_dre_retorna_todas_as_chaves_obrigatorias(): void
    {
        $dre = $this->service->calcularDre([], $this->makeDadosProdutos(), $this->makeParams());

        $chavesObrigatorias = [
            'receita_total_vendas',
            'juros_correcoes',
            'receita_bruta',
            'pis_cofins_outros',
            'iss',
            'outras_deducoes',
            'receita_liquida',
            'custo_terreno',
            'comissao',
            'incorporacao',
            'infra_casas',
            'infra_lotes',
            'area_comum',
            'contrapartidas',
            'canteiro_total',
            'mo_administrativa_total',
            'seguros',
            'assistencia_tecnica',
            'custos_diretos_total',
            'lucro_bruto',
            'despesas_comerciais',
            'marketing',
            'ebitda',
            'outras_despesas_financeiras',
            'despesas_onerosas_bancos',
            'ebit',
            'irpj_csll',
            'lucro_liquido_projeto',
            'custo_total_projeto',
            'indicadores',
        ];

        foreach ($chavesObrigatorias as $chave) {
            $this->assertArrayHasKey($chave, $dre, "Chave ausente no retorno da DRE: {$chave}");
        }
    }

    public function test_calcular_dre_retorna_todas_as_chaves_de_indicadores(): void
    {
        $dre = $this->service->calcularDre([], $this->makeDadosProdutos(), $this->makeParams());

        $chavesIndicadores = [
            'vgv_total',
            'lucro_liquido',
            'margem_liquida_percentual',
            'margem_liquida_sobre_rol',
            'margem_bruta_percentual',
            'margem_ebitda_percentual',
            'margem_ebit_percentual',
            'roi_percentual',
            'total_custos_diretos',
            'custo_total_projeto',
        ];

        foreach ($chavesIndicadores as $chave) {
            $this->assertArrayHasKey($chave, $dre['indicadores'], "Chave de indicador ausente: {$chave}");
        }
    }

    // =========================================================================
    // calcularDre — Coerência aritmética
    // =========================================================================

    public function test_calcular_dre_receita_bruta_e_soma_de_vendas_e_juros(): void
    {
        $dre = $this->service->calcularDre([], $this->makeDadosProdutos(), $this->makeParams());

        $this->assertEqualsWithDelta(
            $dre['receita_total_vendas'] + $dre['juros_correcoes'],
            $dre['receita_bruta'],
            0.02,
            'receita_bruta deve ser receita_total_vendas + juros_correcoes'
        );
    }

    public function test_calcular_dre_lucro_liquido_e_ebit_menos_irpj_csll(): void
    {
        $dre = $this->service->calcularDre([], $this->makeDadosProdutos(), $this->makeParams());

        $this->assertEqualsWithDelta(
            $dre['ebit'] - $dre['irpj_csll'],
            $dre['lucro_liquido_projeto'],
            0.02,
            'lucro_liquido_projeto deve ser ebit - irpj_csll'
        );
    }

    public function test_calcular_dre_ebitda_e_lucro_bruto_menos_despesas_operacionais(): void
    {
        $dre = $this->service->calcularDre([], $this->makeDadosProdutos(), $this->makeParams());

        $this->assertEqualsWithDelta(
            $dre['lucro_bruto'] - $dre['despesas_operacionais_total'],
            $dre['ebitda'],
            0.02,
            'ebitda deve ser lucro_bruto - despesas_operacionais_total'
        );
    }

    public function test_calcular_dre_ebit_e_ebitda_menos_despesas_financeiras_onerosas(): void
    {
        $dre = $this->service->calcularDre([], $this->makeDadosProdutos(), $this->makeParams());

        $this->assertEqualsWithDelta(
            $dre['ebitda'] - $dre['outras_despesas_financeiras'] - $dre['despesas_onerosas_bancos'],
            $dre['ebit'],
            0.02,
            'ebit deve ser ebitda - despesas_financeiras - onerosas'
        );
    }

    public function test_calcular_dre_indicador_margem_liquida_percentual_coerente_com_lucro_e_vgv(): void
    {
        $dadosProdutos = $this->makeDadosProdutos();
        $dre = $this->service->calcularDre([], $dadosProdutos, $this->makeParams());

        $margemEsperada = $dadosProdutos['vgvSemValorTerrenista'] > 0
            ? ($dre['lucro_liquido_projeto'] / $dadosProdutos['vgvSemValorTerrenista']) * 100
            : 0;

        $this->assertEqualsWithDelta(
            $margemEsperada,
            $dre['indicadores']['margem_liquida_percentual'],
            0.05,
            'margem_liquida_percentual deve ser lucro/vgv * 100'
        );
    }

    public function test_calcular_dre_com_vgv_zero_nao_lanca_excecao_e_retorna_indicadores_nulos(): void
    {
        $dadosProdutosZero = $this->makeDadosProdutos([
            'vgv' => 0,
            'vgvSemValorTerrenista' => 0,
            'vgvSemUnidPermutas' => 0,
            'vgvComCorrecao' => 0,
            'correcaoSobreVgv' => 0,
            'produtos' => [
                array_merge(
                    $this->makeDadosProdutos()['produtos'][0],
                    ['vgv_produto' => 0, 'preco' => 0]
                ),
            ],
        ]);

        $dre = $this->service->calcularDre([], $dadosProdutosZero, $this->makeParams());

        $this->assertIsArray($dre);
        $this->assertEquals(0, $dre['receita_total_vendas']);
        $this->assertEquals(0, $dre['indicadores']['margem_liquida_percentual']);
    }

    public function test_calcular_dre_custo_total_projeto_engloba_todos_os_componentes(): void
    {
        $dre = $this->service->calcularDre([], $this->makeDadosProdutos(), $this->makeParams());

        // custo_total_projeto >= custos_diretos + despesas_operacionais
        $this->assertGreaterThanOrEqual(
            $dre['custos_diretos_total'] + $dre['despesas_operacionais_total'],
            $dre['custo_total_projeto'],
            'custo_total_projeto deve incluir ao menos custos_diretos + operacionais'
        );
    }

    public function test_calcular_dre_retorna_valores_positivos_para_cenario_realista(): void
    {
        $dre = $this->service->calcularDre([], $this->makeDadosProdutos(), $this->makeParams());

        $this->assertGreaterThanOrEqual(0, $dre['receita_total_vendas']);
        $this->assertGreaterThan(0, $dre['receita_bruta']);
        $this->assertGreaterThan(0, $dre['custo_total_projeto']);
    }

    // =========================================================================
    // calcularDre — Efeito de parâmetros
    // =========================================================================

    public function test_calcular_dre_comissao_influencia_custo_direto(): void
    {
        // A comissão na DRE é calculada sobre abs(custoTerreno), portanto
        // precisamos de um custo de terreno não-nulo para que a comissão varie.
        $paramsBase = $this->makeParams(['compraTerreno' => 1_000_000.0]);

        $semComissao = $this->service->calcularDre([], $this->makeDadosProdutos(), array_merge($paramsBase, ['percentualComissao' => 0]));
        $comComissao = $this->service->calcularDre([], $this->makeDadosProdutos(), array_merge($paramsBase, ['percentualComissao' => 0.05]));

        $this->assertGreaterThanOrEqual(
            $semComissao['comissao'],
            $comComissao['comissao'],
            'Linha de comissão na DRE deve ser maior com percentual maior sobre o mesmo custo terreno'
        );

        $this->assertGreaterThanOrEqual(
            $semComissao['custos_diretos_total'],
            $comComissao['custos_diretos_total'],
            'Custo direto total deve ser maior com comissão'
        );
    }

    public function test_calcular_dre_compra_terreno_aumenta_custo_direto(): void
    {
        $semTerreno = $this->service->calcularDre([], $this->makeDadosProdutos(), $this->makeParams(['compraTerreno' => 0]));
        $comTerreno = $this->service->calcularDre([], $this->makeDadosProdutos(), $this->makeParams(['compraTerreno' => 1_000_000]));

        $this->assertGreaterThan(
            $semTerreno['custo_terreno'],
            $comTerreno['custo_terreno'],
            'Custo do terreno deve crescer com compra_terreno'
        );
    }

    public function test_calcular_dre_mais_meses_obra_aumenta_custo_canteiro(): void
    {
        $params18 = $this->makeParams(['mesesObra' => 18]);
        $params36 = $this->makeParams(['mesesObra' => 36]);

        $dre18 = $this->service->calcularDre([], $this->makeDadosProdutos(), $params18);
        $dre36 = $this->service->calcularDre([], $this->makeDadosProdutos(), $params36);

        $this->assertGreaterThan(
            $dre18['canteiro_total'],
            $dre36['canteiro_total'],
            'Canteiro total deve ser maior com mais meses de obra'
        );
    }

    // =========================================================================
    // calcularReceitas — Comportamento com caches zerados
    // =========================================================================

    public function test_calcular_receitas_em_periodo_incorporacao_retorna_zero(): void
    {
        // Datas: período incorporação é antes do lançamento
        $datas = $this->makeDatas();
        $mesAntes = $datas['inicioIncorporacao']->format('Y-m');

        // Com caches zerados (service recém criado), RP = 0, RT = 0, MO = 0
        $receitas = $this->service->calcularReceitas(
            $mesAntes,
            $this->makeDadosProdutos(),
            $datas,
            $this->makeParams(),
        );

        $this->assertEquals(
            0.0,
            $receitas['total'],
            'Receita total deve ser zero no período de incorporação sem caches pré-calculados'
        );
        $this->assertEquals(0.0, $receitas['detalhes']['recursos_proprios']['total_recursos_proprios']);
        $this->assertEquals(0.0, $receitas['detalhes']['recebimento_terreno']['recebimento_total_terreno']);
        $this->assertEquals(0.0, $receitas['detalhes']['medicao_obra']['recebimento_total_medicao']);
    }

    public function test_calcular_receitas_total_igual_a_soma_dos_detalhes(): void
    {
        $datas = $this->makeDatas();
        $mesLancamento = $datas['dataLancamento']->format('Y-m');

        $receitas = $this->service->calcularReceitas(
            $mesLancamento,
            $this->makeDadosProdutos(),
            $datas,
            $this->makeParams(),
        );

        $somaDetalhes = $receitas['detalhes']['total'];

        $this->assertEqualsWithDelta(
            $somaDetalhes,
            $receitas['total'],
            0.02,
            'total deve ser a soma exata dos detalhes de receita'
        );
    }

    public function test_calcular_receitas_retorna_chaves_obrigatorias(): void
    {
        $datas = $this->makeDatas();
        $mes = $datas['dataLancamento']->format('Y-m');

        $receitas = $this->service->calcularReceitas(
            $mes,
            $this->makeDadosProdutos(),
            $datas,
            $this->makeParams(),
        );

        $this->assertArrayHasKey('total', $receitas);
        $this->assertArrayHasKey('juros_correcao', $receitas);
        $this->assertArrayHasKey('detalhes', $receitas);
        $this->assertArrayHasKey('recursos_proprios', $receitas['detalhes']);
        $this->assertArrayHasKey('recebimento_terreno', $receitas['detalhes']);
        $this->assertArrayHasKey('medicao_obra', $receitas['detalhes']);
    }

    // =========================================================================
    // calcularDespesas — Coerência aritmética
    // =========================================================================

    public function test_calcular_despesas_retorna_chaves_obrigatorias(): void
    {
        $datas = $this->makeDatas();
        $mes = $datas['dataLancamento']->format('Y-m');
        $receitas = ['total' => 100_000.0, 'juros_correcao' => 0.0, 'detalhes' => []];

        $despesas = $this->service->calcularDespesas(
            $mes,
            $receitas,
            $this->makeDadosProdutos(),
            $datas,
            $this->makeParams(),
        );

        $this->assertArrayHasKey('total', $despesas);
        $this->assertArrayHasKey('detalhes', $despesas);
        $this->assertArrayHasKey('categorias', $despesas);
        $this->assertArrayHasKey('custo_direto', $despesas['categorias']);
        $this->assertArrayHasKey('impostos', $despesas['categorias']);
        $this->assertArrayHasKey('custos_operacionais', $despesas['categorias']);
        $this->assertArrayHasKey('custos_financeiros', $despesas['categorias']);
    }

    public function test_calcular_despesas_soma_de_categorias_igual_ao_total(): void
    {
        $datas = $this->makeDatas();
        $mes = $datas['dataLancamento']->format('Y-m');
        $receitas = ['total' => 200_000.0, 'juros_correcao' => 0.0, 'detalhes' => []];

        $despesas = $this->service->calcularDespesas(
            $mes,
            $receitas,
            $this->makeDadosProdutos(),
            $datas,
            $this->makeParams(),
        );

        $somaCategorias = array_sum($despesas['categorias']);

        $this->assertEqualsWithDelta(
            $despesas['total'],
            $somaCategorias,
            0.02,
            'total de despesas deve ser igual à soma das categorias'
        );
    }

    public function test_calcular_despesas_tributos_proporcionais_a_receita(): void
    {
        $datas = $this->makeDatas();
        $mes = $datas['dataLancamento']->format('Y-m');

        $receitaBaixa = ['total' => 100_000.0, 'juros_correcao' => 0.0, 'detalhes' => []];
        $receitaAlta = ['total' => 500_000.0, 'juros_correcao' => 0.0, 'detalhes' => []];

        $despesasBaixas = $this->service->calcularDespesas($mes, $receitaBaixa, $this->makeDadosProdutos(), $datas, $this->makeParams());
        $despesasAltas = $this->service->calcularDespesas($mes, $receitaAlta, $this->makeDadosProdutos(), $datas, $this->makeParams());

        $this->assertGreaterThan(
            $despesasBaixas['categorias']['impostos'],
            $despesasAltas['categorias']['impostos'],
            'Impostos devem ser maiores com receita maior'
        );
    }

    public function test_calcular_despesas_exclui_lotes_da_base_de_deducoes(): void
    {
        $datas = $this->makeDatas();
        $mes = $datas['dataLancamento']->format('Y-m');
        $receitas = ['total' => 100_000.0, 'juros_correcao' => 0.0, 'detalhes' => []];
        $dadosProdutos = $this->makeDadosProdutos([
            'vgv' => 1_000_000.0,
            'vgvSemValorTerrenista' => 1_000_000.0,
            'vgvSemUnidPermutas' => 1_000_000.0,
            'produtos' => [
                ['nome' => 'Apto 2 Dorm', 'vgv_produto' => 600_000.0],
                ['nome' => 'Lote 250m2', 'vgv_produto' => 400_000.0],
            ],
        ]);
        $params = $this->makeParams(['percentualPisCofins' => 0.04]);

        $despesas = $this->service->calcularDespesas($mes, $receitas, $dadosProdutos, $datas, $params);

        $this->assertSame(4300.0, $despesas['detalhes']['Deduções']);
        $this->assertSame(2400.0, $despesas['detalhes']['Deduções - RET/LP Imóveis']);
        $this->assertSame(1600.0, $despesas['detalhes']['Deduções - RET/LP Lotes']);
        $this->assertSame(4300.0, $despesas['categorias']['impostos']);
    }

    public function test_calcular_despesas_periodo_obra_inclui_custo_obra_positivo(): void
    {
        $datas = $this->makeDatas();
        $mesObra = $datas['inicioObra']->format('Y-m');
        $receitas = ['total' => 0.0, 'juros_correcao' => 0.0, 'detalhes' => []];

        $despesas = $this->service->calcularDespesas(
            $mesObra,
            $receitas,
            $this->makeDadosProdutos(),
            $datas,
            $this->makeParams(),
        );

        $this->assertArrayHasKey('Obra', $despesas['detalhes']);
        $this->assertGreaterThan(
            0.0,
            $despesas['detalhes']['Obra'],
            'Custo de obra deve ser positivo no período de construção'
        );
    }

    public function test_calcular_despesas_periodo_incorporacao_nao_tem_custo_de_obra(): void
    {
        $datas = $this->makeDatas();
        $mesIncorp = $datas['inicioIncorporacao']->format('Y-m');
        $receitas = ['total' => 0.0, 'juros_correcao' => 0.0, 'detalhes' => []];

        $despesas = $this->service->calcularDespesas(
            $mesIncorp,
            $receitas,
            $this->makeDadosProdutos(),
            $datas,
            $this->makeParams(),
        );

        $this->assertArrayNotHasKey(
            'Obra',
            $despesas['detalhes'],
            'Não deve haver custo de Obra no período de incorporação'
        );
    }

    public function test_calcular_despesas_despesas_financeiras_zero_quando_receita_zero(): void
    {
        $datas = $this->makeDatas();
        $mes = $datas['dataLancamento']->format('Y-m');
        $params = $this->makeParams([
            'percentualProdutosCef' => 0.0,
            'percentualOutrasDespesasFinanceiras' => 0.0,
        ]);
        $receitas = ['total' => 0.0, 'juros_correcao' => 0.0, 'detalhes' => []];

        $despesas = $this->service->calcularDespesas($mes, $receitas, $this->makeDadosProdutos(), $datas, $params);

        $this->assertEquals(
            0.0,
            $despesas['categorias']['custos_financeiros'],
            'Custos financeiros devem ser zero quando receita e percentuais são zero'
        );
    }

    // =========================================================================
    // calcularDre — Consistência em chamadas repetidas na mesma instância
    // =========================================================================

    public function test_calcular_dre_retorna_mesmo_resultado_em_chamadas_consecutivas(): void
    {
        $dadosProdutos = $this->makeDadosProdutos();
        $params = $this->makeParams();

        $dre1 = $this->service->calcularDre([], $dadosProdutos, $params);
        $dre2 = $this->service->calcularDre([], $dadosProdutos, $params);

        $this->assertEquals(
            $dre1['lucro_liquido_projeto'],
            $dre2['lucro_liquido_projeto'],
            'calcularDre deve ser idempotente entre chamadas consecutivas'
        );
    }

    public function test_calcular_dre_resultado_e_determinístico_com_mesmos_inputs(): void
    {
        $dadosProdutos = $this->makeDadosProdutos();
        $params = $this->makeParams();

        // Instâncias diferentes devem produzir o mesmo resultado
        $serviceA = $this->makeService();
        $serviceB = $this->makeService();

        $dreA = $serviceA->calcularDre([], $dadosProdutos, $params);
        $dreB = $serviceB->calcularDre([], $dadosProdutos, $params);

        $this->assertEquals(
            $dreA['lucro_liquido_projeto'],
            $dreB['lucro_liquido_projeto'],
            'Instâncias diferentes com mesmos inputs devem produzir o mesmo lucro_liquido_projeto'
        );
    }

    // =========================================================================
    // calcularReceitas — Consistência em chamadas repetidas na mesma instância
    // =========================================================================

    public function test_calcular_receitas_em_periodo_incorporacao_idempotente(): void
    {
        $datas = $this->makeDatas();
        $mes = $datas['inicioIncorporacao']->format('Y-m');
        $dados = $this->makeDadosProdutos();
        $params = $this->makeParams();

        // Chamar duas vezes: a segunda não deve acumular estado indevido nos detalhes
        $receitas1 = $this->service->calcularReceitas($mes, $dados, $datas, $params);
        $receitas2 = $this->service->calcularReceitas($mes, $dados, $datas, $params);

        // Em período de incorporação, sem caches, ambas devem retornar 0
        $this->assertEquals(
            $receitas1['detalhes'],
            $receitas2['detalhes'],
            'Detalhes de receitas devem ser iguais em chamadas consecutivas no mesmo mês'
        );
    }
}
