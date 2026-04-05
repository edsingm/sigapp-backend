<?php

namespace App\Services\Tenant\Viabilidade;

use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * ViabilidadeUnificadoService — Motor de cálculo financeiro de viabilidade imobiliária.
 *
 * Quatro responsabilidades públicas:
 *  1. gerarFluxoMensal()  — orquestra o pipeline completo e devolve fluxo de caixa
 *  2. calcularReceitas()  — receitas de um mês específico
 *  3. calcularDespesas()  — despesas de um mês específico
 *  4. calcularDre()       — DRE consolidada do projeto
 *
 * O estado mutável do cálculo é isolado em ViabilidadeFluxoContext, garantindo
 * que chamadas consecutivas na mesma instância não acumulem dados entre si.
 */
class ViabilidadeUnificadoService
{
    // ─── Taxas de correção monetária (fixas, baseadas em histórico do projeto) ───
    private const TAXA_CORRECAO_OBRA_ANUAL  = 0.05;   // 5% a.a.
    private const TAXA_CORRECAO_POS_ANUAL   = 0.045;  // 4.5% a.a.
    private const JUROS_POS_CHAVE_MENSAL    = 0.01;   // 1% a.m.
    private const PRAZO_POS_CHAVE_PADRAO    = 36;     // parcelas pós-chave
    private const PERCENTUAL_FINANCIAMENTO_CEF = 0.80; // 80% do VGV via CEF

    public function __construct(
        protected readonly CurvaService $curvaService,
        protected readonly ImpostosService $impostosService,
    ) {}

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * FUNÇÃO 1: GERAR FLUXO MENSAL
     * ═══════════════════════════════════════════════════════════════════════
     * Orquestra todo o cálculo e retorna fluxo de caixa completo.
     * Cada chamada cria um ViabilidadeFluxoContext limpo, garantindo
     * que o estado não vaze entre invocações consecutivas.
     */
    public function gerarFluxoMensal(
        int $terrenoId,
        Viabilidade|int|null $viabilidadeRef = null,
        ?array $customProdutos = null
    ): array {
        try {
            // 1. Buscar e processar dados
            $terreno       = $this->buscarTerreno($terrenoId);
            $viabilidade   = $this->buscarViabilidade($terrenoId, $viabilidadeRef);
            $params        = $this->montarParametros($viabilidade);
            $dadosProdutos = $this->processarProdutos($terreno, $params, $customProdutos);

            // Validação: curvas obrigatórias devem estar preenchidas nos produtos
            $validacaoCurvas = $this->curvaService->validarCurvasObrigatorias($dadosProdutos['produtos']);
            if (! $validacaoCurvas['valid']) {
                throw new Exception(
                    'Curvas obrigatórias não preenchidas nos produtos: ' . implode(', ', $validacaoCurvas['faltando'])
                );
            }

            if ($dadosProdutos['totalUnidades'] === 0 || $dadosProdutos['vgv'] === 0) {
                throw new Exception('Não foi possível calcular dados válidos dos produtos.');
            }

            // Agregar curva_obra de todos os produtos (peso = quantidade_unidades)
            $dadosProdutos['curvaObraAgregada'] = $this->agregarCurvaObra($dadosProdutos['produtos']);

            // 2. Calcular datas dos períodos
            $datas = $this->calcularPeriodos($dadosProdutos['dataInicio'], $params);

            // 3. Montar contexto limpo e pré-popular caches
            $ctx = new ViabilidadeFluxoContext();
            $this->preCalcularRecursosProprios($dadosProdutos['produtos'], $datas, $params, $ctx);
            $this->inicializarCachesCef($dadosProdutos, $datas, $ctx);

            // 4. Iterar mês a mês
            $fluxo          = [];
            $saldoAcumulado = 0.0;
            $fluxoTir       = [];  // com CEF — base para tir_operacional
            $fluxoTirSemCef = [];  // sem CEF — base para tir_sem_cef
            $totais         = [
                'receita'              => 0.0,
                'custo_direto'         => 0.0,
                'impostos'             => 0.0,
                'custos_operacionais'  => 0.0,
                'custos_financeiros'   => 0.0,
                'lucro'                => 0.0,
            ];

            $periodo = CarbonPeriod::create($datas['inicioIncorporacao'], '1 month', $datas['fimPos']);

            foreach ($periodo as $data) {
                $mes = $data->format('Y-m');

                $receitas = $this->calcularReceitas($mes, $dadosProdutos, $datas, $params, $ctx);
                $despesas = $this->calcularDespesas($mes, $receitas, $dadosProdutos, $datas, $params, $ctx);

                $lucroMes        = $receitas['total'] - $despesas['total'];
                $saldoAcumulado += $lucroMes;

                // TIR sem CEF: apenas Recursos Próprios como receita
                $receitaRpMes    = $receitas['detalhes']['Recursos Próprios'] ?? 0.0;
                $lucroSemCefMes  = $receitaRpMes - $despesas['total'];

                $unidadesVendidasMes = ceil($ctx->vendasPorMes[$mes] ?? 0);

                $fluxo[$mes] = [
                    'periodo'          => $this->identificarPeriodo($data, $datas),
                    'receita_total'    => round($receitas['total'], 2),
                    'receitas'         => $receitas['detalhes'],
                    'despesas'         => array_filter($despesas['detalhes'], fn($v) => abs($v) > 0.01),
                    'custos_totais'    => round($despesas['total'], 2),
                    'lucro'            => round($lucroMes, 2),
                    'saldo_acumulado'  => round($saldoAcumulado, 2),
                    'unidades_vendidas' => round($unidadesVendidasMes, 2),
                ];

                $fluxoTir[]       = ['data' => $data->copy(), 'valor' => $lucroMes];
                $fluxoTirSemCef[] = ['data' => $data->copy(), 'valor' => $lucroSemCefMes];

                $totais['receita']             += $receitas['total'];
                $totais['custo_direto']        += $despesas['categorias']['custo_direto'];
                $totais['impostos']            += $despesas['categorias']['impostos'];
                $totais['custos_operacionais'] += $despesas['categorias']['custos_operacionais'];
                $totais['custos_financeiros']  += $despesas['categorias']['custos_financeiros'];
                $totais['lucro']               += $lucroMes;
            }

            [$fluxoFinanceiro, $indicadoresFinanceiros] = $this->calcularIndicadoresFinanceiros($fluxo, $datas, $params, $dadosProdutos);
            $indicadoresVso        = $this->calcularIndicadoresVso($fluxo, $dadosProdutos);
            $indicadoresVsoJanelas = $this->calcularIndicadoresVsoJanelas($fluxo, $dadosProdutos);

            $indicadores = [
                'tir_operacional'             => $this->calcularTir($fluxoTir),
                'tir_sem_cef'                 => $this->calcularTir($fluxoTirSemCef),
                'exposicao_maxima_operacional' => collect($fluxo)->min('saldo_acumulado'),
                'margem_liquida'              => $totais['receita'] > 0
                    ? ($totais['lucro'] / $totais['receita'])
                    : 0.0,
            ];

            // 5. Calcular DRE consolidada
            $dre                        = $this->calcularDre($fluxo, $dadosProdutos, $params);
            $dreContabilPoc             = $this->calcularDreContabilPoc($fluxo, $dre, $dadosProdutos);
            $dreContabilPocMensal       = $this->calcularQuadroPocMensal($fluxo, $dre, $dadosProdutos);
            $dreContabilPocMensalBlocos = $this->calcularQuadroPocMensalPorBlocos($fluxo, $dre, $dadosProdutos);

            return [
                'terreno'                      => $terreno,
                'vgv'                          => $dadosProdutos['vgvSemValorTerrenista'],
                'totalUnidades'                => $dadosProdutos['totalUnidades'],
                'unidadesPermuta'              => $dadosProdutos['permutas'],
                'areaConstruida'               => $dadosProdutos['areaConstruida'],
                'custoTotal'                   => $dre['custo_total_projeto'],
                'produtos'                     => $dadosProdutos['produtos'],
                'dre_itens'                    => $dre,
                'dre_contabil_poc'             => $dreContabilPoc,
                'dre_contabil_poc_mensal'      => $dreContabilPocMensal,
                'dre_contabil_poc_mensal_blocos' => $dreContabilPocMensalBlocos,
                'indicadores'                  => array_merge($dre['indicadores'], $indicadores, $indicadoresFinanceiros, $indicadoresVso, $indicadoresVsoJanelas),
                'dados_produtos'               => [
                    'total_unidades'        => $dadosProdutos['totalUnidades'],
                    'unidades_permuta'      => $dadosProdutos['permutas'],
                    'area_construida_total' => $dadosProdutos['areaConstruida'],
                ],
                'fluxo_mensal'             => $fluxo,
                'fluxo_mensal_financeiro'  => $fluxoFinanceiro,
                'totais'                   => $totais,
                'parametros_utilizados'    => $params,
            ];
        } catch (Exception $e) {
            Log::error('Erro ao gerar fluxo mensal: ' . $e->getMessage(), [
                'terrenoId' => $terrenoId,
                'trace'     => $e->getTraceAsString(),
            ]);
            throw new Exception('Erro ao gerar fluxo mensal: ' . $e->getMessage());
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * FUNÇÃO 2: CALCULAR RECEITAS
     * ═══════════════════════════════════════════════════════════════════════
     * Calcula todas as receitas de um mês específico.
     *
     * Quando chamado diretamente (fora do pipeline de gerarFluxoMensal) recebe
     * um contexto opcional — sem contexto, os caches CEF são zero, logo RT e MO
     * retornam 0, o que é o comportamento correto para testes unitários isolados.
     */
    public function calcularReceitas(
        string $mes,
        array $dadosProdutos,
        array $datas,
        array $params,
        ?ViabilidadeFluxoContext $ctx = null
    ): array {
        $ctx ??= new ViabilidadeFluxoContext();

        // Atualizar acumulado de vendas com o mês atual
        $ctx->vendasAcumuladas += $ctx->vendasPorMes[$mes] ?? 0.0;

        // 1. Recursos Próprios (do cache pré-calculado)
        $rp       = $ctx->recursosProprios[$mes] ?? [];
        $totalRp  = ($rp['sinal'] ?? 0.0) + ($rp['parcelas_obra'] ?? 0.0) + ($rp['parcelas_pos'] ?? 0.0);

        // 2. Recurso Terrenos (CEF)
        $rt = $this->calcularRecursoTerrenos($mes, $dadosProdutos, $datas, $ctx);

        // 3. Medição de Obra (CEF)
        $mo = $this->calcularMedicaoObra($mes, $dadosProdutos, $datas, $params, $ctx);

        $total = $totalRp + $rt['valor'] + $mo['valor'];

        return [
            'total'         => $total,
            'juros_correcao' => ($rp['juros'] ?? 0.0) + ($rp['correcao'] ?? 0.0),
            'detalhes'      => [
                'Recursos Próprios' => round($totalRp, 2),
                'Recurso Terrenos'  => round($rt['valor'], 2),
                'Medição Obra'      => round($mo['valor'], 2),
            ],
        ];
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * FUNÇÃO 3: CALCULAR DESPESAS
     * ═══════════════════════════════════════════════════════════════════════
     * Calcula todas as despesas de um mês específico.
     */
    public function calcularDespesas(
        string $mes,
        array $receitas,
        array $dadosProdutos,
        array $datas,
        array $params,
        ?ViabilidadeFluxoContext $ctx = null
    ): array {
        $ctx         ??= new ViabilidadeFluxoContext();
        $dataAtual   = $this->parseMes($mes);
        $periodo     = $this->identificarPeriodo($dataAtual, $datas);
        $vgv         = $dadosProdutos['vgv'];
        $custoObra   = $this->custoObraTotal($dadosProdutos);

        // 1. Custos Diretos (por período)
        $diretos = $this->calcularCustosDiretos($mes, $periodo, $datas, $params, $vgv, $custoObra, $dadosProdutos);

        // 2. Tributos
        $tributos = $this->impostosService->calcularTributosPorProduto(
            $receitas['total'],
            $receitas['juros_correcao'],
            $dadosProdutos['produtos'],
            $vgv,
            $params
        );

        // 3. Custos Operacionais
        $operacionais = $this->calcularCustosOperacionais($mes, $dadosProdutos, $datas, $params, $ctx);

        // 4. Custos Financeiros
        $financeiros = $receitas['total'] * ($params['percentualProdutosCef'] + $params['percentualOutrasDespesasFinanceiras']);

        // 5. Custo Terreno (proporcional à receita)
        $custoTerreno = $this->calcularCustoTerreno($mes, $receitas['total'], $dadosProdutos, $params);

        $detalhesOperacionais = [];
        foreach ($operacionais['detalhes'] as $nome => $valor) {
            $detalhesOperacionais['Operacional - ' . $nome] = round($valor, 2);
        }

        $total = $diretos['total'] + $tributos + $operacionais['total'] + $financeiros + $custoTerreno;

        return [
            'total'    => $total,
            'detalhes' => array_merge($diretos['detalhes'], [
                'Tributos'      => round($tributos, 2),
                'Operacional'   => round($operacionais['total'], 2),
                'Financeiro'    => round($financeiros, 2),
                'Custo Terreno' => round($custoTerreno, 2),
            ], $detalhesOperacionais),
            'categorias' => [
                'custo_direto'        => $diretos['total'] + $custoTerreno,
                'impostos'            => $tributos,
                'custos_operacionais' => $operacionais['total'],
                'custos_financeiros'  => $financeiros,
            ],
        ];
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * FUNÇÃO 4: CALCULAR DRE
     * ═══════════════════════════════════════════════════════════════════════
     * Calcula a DRE consolidada a partir do fluxo mensal.
     * Delegado em quatro blocos: receitas, custos diretos, operacionais e financeiras.
     */
    public function calcularDre(array $fluxo, array $dadosProdutos, array $params): array
    {
        $vgv              = $dadosProdutos['vgv'];
        $vgvSemTerrenista = $dadosProdutos['vgvSemValorTerrenista'];
        $vgvSemPermutas   = $dadosProdutos['vgvSemUnidPermutas'];
        $totalUnidades    = $dadosProdutos['totalUnidades'];

        // ── Receitas ──────────────────────────────────────────────────────────
        $receitaTotalVendas = $vgvSemTerrenista;
        $jurosCorrecoes     = $dadosProdutos['correcaoSobreVgv'];
        $receitaBruta       = $receitaTotalVendas + $jurosCorrecoes;

        $impostos       = $this->impostosService->calcularImpostosDre($dadosProdutos['produtos'], $vgvSemTerrenista);
        $receitaLiquida = $receitaBruta - $impostos['total'];

        // ── Custos diretos ────────────────────────────────────────────────────
        [
            $custoTerreno,
            $comissao,
            $incorporacao,
            $infraCasas,
            $infraLotes,
            $areaComum,
            $contrapartidas,
            $canteiroTotal,
            $moAdministrativaTotal,
            $seguros,
            $assistenciaTecnica,
            $jurosPJ,
            $custosDiretosTotal,
            $custoTotalObra
        ]
            = $this->calcularCustosDiretosDre($dadosProdutos, $params, $totalUnidades, $vgv);

        $lucroBruto = $receitaLiquida - $custosDiretosTotal;

        // ── Despesas operacionais ─────────────────────────────────────────────
        [
            $despesasComerciais,
            $marketingTotal,
            $itbiIptu,
            $registroTotal,
            $txMedicao,
            $contratosCef,
            $produtosCef,
            $despesasOperacionaisTotal
        ]
            = $this->calcularDespesasOperacionaisDre($dadosProdutos, $params);

        $ebitda = $lucroBruto - $despesasOperacionaisTotal;

        // ── Despesas financeiras ──────────────────────────────────────────────
        $outrasDespFinanceiras = $params['percentualOutrasDespesasFinanceiras'] * $receitaTotalVendas;
        $despesasOnerosas      = $jurosPJ['juros_totais'];
        $ebit                  = $ebitda - $outrasDespFinanceiras - $despesasOnerosas;

        // ── Resultado ─────────────────────────────────────────────────────────
        $irpjCsll     = $impostos['total_ir_csll'];
        $lucroLiquido = $ebit - $irpjCsll;

        $custoTotalProjeto = $custosDiretosTotal + $despesasOperacionaisTotal
            + $outrasDespFinanceiras + $despesasOnerosas + $irpjCsll + $impostos['total'];

        return [
            'receita_total_vendas'         => round($receitaTotalVendas, 2),
            'juros_correcoes'              => round($jurosCorrecoes, 2),
            'receita_bruta'                => round($receitaBruta, 2),
            'pis_cofins_outros'            => round($impostos['pis'] + $impostos['cofins'], 2),
            'iss'                          => round($impostos['iss'], 2),
            'outras_deducoes'              => round($impostos['outras_deducoes'], 2),
            'receita_liquida'              => round($receitaLiquida, 2),
            'custo_terreno'                => round($custoTerreno, 2),
            'comissao'                     => round($comissao, 2),
            'incorporacao'                 => round($incorporacao, 2),
            'incorporacao_detalhes'        => [
                'ri'       => round($incorporacao * $params['incorporacaoRi'], 2),
                'entrega'  => round($incorporacao * $params['incorporacaoEntrega'], 2),
                'projetos' => round($incorporacao * (1 - $params['incorporacaoRi'] - $params['incorporacaoEntrega']), 2),
            ],
            'infra_casas'                  => round($infraCasas, 2),
            'infra_lotes'                  => round($infraLotes, 2),
            'area_comum'                   => round($areaComum, 2),
            'contrapartidas'               => round($contrapartidas, 2),
            'canteiro_total'               => round($canteiroTotal, 2),
            'mo_administrativa_total'      => round($moAdministrativaTotal, 2),
            'seguros'                      => round($seguros, 2),
            'assistencia_tecnica'          => round($assistenciaTecnica, 2),
            'custo_total_obra'             => round($custoTotalObra, 2),
            'custos_diretos_total'         => round($custosDiretosTotal, 2),
            'lucro_bruto'                  => round($lucroBruto, 2),
            'despesas_comerciais'          => round($despesasComerciais['total'], 2),
            'despesas_comerciais_detalhes' => $despesasComerciais['detalhes'],
            'marketing'                    => round($marketingTotal, 2),
            'itbi_iptu'                    => round($itbiIptu, 2),
            'registro'                     => round($registroTotal, 2),
            'tx_medicao_contratacao'       => round($txMedicao, 2),
            'contratos_caixa'              => round($contratosCef, 2),
            'produtos_caixa'               => round($produtosCef, 2),
            'despesas_operacionais_total'  => round($despesasOperacionaisTotal, 2),
            'ebitda'                       => round($ebitda, 2),
            'outras_despesas_financeiras'  => round($outrasDespFinanceiras, 2),
            'despesas_onerosas_bancos'     => round($despesasOnerosas, 2),
            'juros_pj'                     => round($jurosPJ['juros_totais'], 2),
            'juros_pj_detalhes'            => [
                'valor_antecipado'      => round($jurosPJ['valor_antecipado'], 2),
                'taxa_mensal'           => $jurosPJ['taxa_mensal'],
                'carencia_meses'        => $jurosPJ['carencia_meses'] ?? 0,
                'amortizacao_parcelas'  => $jurosPJ['amortizacao_parcelas'] ?? 0,
            ],
            'ebit'                         => round($ebit, 2),
            'irpj_csll'                    => round($irpjCsll, 2),
            'lucro_liquido_projeto'        => round($lucroLiquido, 2),
            'custo_total_projeto'          => round($custoTotalProjeto, 2),
            'indicadores'                  => [
                'vgv_total'                            => round($receitaTotalVendas, 2),
                'lucro_liquido'                        => round($lucroLiquido, 2),
                'margem_liquida_percentual'            => $receitaTotalVendas > 0 ? round(($lucroLiquido / $receitaTotalVendas) * 100, 2) : 0,
                'margem_liquida_sobre_rol'             => $receitaLiquida > 0     ? round(($lucroLiquido / $receitaLiquida) * 100, 2)     : 0,
                'margem_liquida_sobre_vgv_sem_permuta' => $vgvSemPermutas > 0    ? round(($lucroLiquido / $vgvSemPermutas) * 100, 2)     : 0,
                'margem_bruta_percentual'              => $receitaLiquida > 0     ? round(($lucroBruto / $receitaLiquida) * 100, 2)       : 0,
                'margem_ebitda_percentual'             => $receitaLiquida > 0     ? round(($ebitda / $receitaLiquida) * 100, 2)           : 0,
                'margem_ebit_percentual'               => $receitaLiquida > 0     ? round(($ebit / $receitaLiquida) * 100, 2)             : 0,
                'roi_percentual'                       => $custosDiretosTotal > 0 ? round(($lucroLiquido / $custosDiretosTotal) * 100, 2) : 0,
                'total_custos_diretos'                 => round($custosDiretosTotal, 2),
                'custo_total_projeto'                  => round($custoTotalProjeto, 2),
            ],
        ];
    }

    /**
     * Calcula o bloco de custos diretos da DRE.
     * Retorna array posicional para desestruturação no chamador.
     *
     * @return array{0:float,1:float,2:float,3:float,4:float,5:float,6:float,7:float,8:float,9:float,10:float,11:array,12:float,13:float}
     */
    private function calcularCustosDiretosDre(
        array $dadosProdutos,
        array $params,
        int $totalUnidades,
        float $vgv
    ): array {
        // Custo Terreno: permuta física + permuta financeira + compra + infra proprietário
        $custoTerreno = $params['compraTerreno']
            + ($params['parceriaVgv'] * $dadosProdutos['vgvComCorrecao'])
            + ($dadosProdutos['permutas'] * ($dadosProdutos['custoCasaM2'] ?? 0))
            + ($dadosProdutos['permutas'] * ($dadosProdutos['custoInfraM2'] ?? 0));

        // Comissão: sobre custo terreno (planilha DRE J62 = J60 × D62)
        $comissao = $params['percentualComissao'] * abs($custoTerreno);

        $incorporacao        = $params['percentualIncorporacao'] * $vgv;
        $infraCasas          = $dadosProdutos['custoObraHabitacao'];
        $infraLotes          = $dadosProdutos['custoInfraestrutura'] + $dadosProdutos['custoNaoIncidente'];
        $areaComum           = $params['custoAreaComum'] * $totalUnidades;
        $contrapartidas      = $params['percentualContrapartidas'] * $vgv;
        $canteiroTotal       = $params['canteiroMensal'] * $params['mesesObra'];
        $moAdministrativaTotal = $params['moAdministrativa'] * $params['mesesObra'];
        $seguros             = $this->calcularSegurosPorTipologia($dadosProdutos, $params);

        $custoTotalObra = $infraCasas + $infraLotes + $areaComum + $contrapartidas + $canteiroTotal;

        // Assistência Técnica: % sobre (casas+lotes+contrapartidas+área comum)
        $baseAssistencia   = $infraCasas + $infraLotes + $contrapartidas + $areaComum;
        $assistenciaTecnica = $params['percentualAssistenciaTecnica'] * $baseAssistencia;

        $jurosPJ = $this->impostosService->calcularJurosPJ(
            $custoTotalObra,
            $params['mesesObra'],
            'composto',
            $params['taxaJurosPj'],
            $params['percentualAntecipacaoPj'],
            $custoTerreno,
            $params['carenciaPjMeses'],
            $params['amortizacaoPjParcelas']
        );

        $custosDiretosTotal = $custoTerreno + $comissao + $incorporacao + $infraCasas + $infraLotes
            + $areaComum + $contrapartidas + $canteiroTotal + $moAdministrativaTotal + $seguros + $assistenciaTecnica;

        return [
            $custoTerreno,
            $comissao,
            $incorporacao,
            $infraCasas,
            $infraLotes,
            $areaComum,
            $contrapartidas,
            $canteiroTotal,
            $moAdministrativaTotal,
            $seguros,
            $assistenciaTecnica,
            $jurosPJ,
            $custosDiretosTotal,
            $custoTotalObra,
        ];
    }

    /**
     * Calcula o bloco de despesas operacionais da DRE.
     * Retorna array posicional para desestruturação no chamador.
     *
     * @return array{0:array,1:float,2:float,3:float,4:float,5:float,6:float,7:float}
     */
    private function calcularDespesasOperacionaisDre(array $dadosProdutos, array $params): array
    {
        $despesasComerciais        = $this->calcularDespesasComerciaisDetalhadas($dadosProdutos, $params);
        $marketingTotal            = $this->calcularMarketingDetalhado($dadosProdutos, $params);
        $itbiIptu                  = $this->calcularItbiPorTipologia($dadosProdutos, $params);
        $registroTotal             = $this->calcularRegistroPorTipologia($dadosProdutos, $params);
        $txMedicao                 = $this->calcularTxMedicao($dadosProdutos, $params);
        $contratosCef              = $this->calcularContratosCef($dadosProdutos, $params);
        $produtosCef               = $this->calcularProdutosCefPorTipologia($dadosProdutos, $params);
        $despesasOperacionaisTotal = $despesasComerciais['total'] + $marketingTotal + $itbiIptu
            + $registroTotal + $txMedicao + $contratosCef + $produtosCef;

        return [
            $despesasComerciais,
            $marketingTotal,
            $itbiIptu,
            $registroTotal,
            $txMedicao,
            $contratosCef,
            $produtosCef,
            $despesasOperacionaisTotal,
        ];
    }

    private function calcularDespesasComerciaisDetalhadas(array $dadosProdutos, array $params): array
    {
        $vgvSemTerrenista    = $dadosProdutos['vgvSemValorTerrenista'] ?? 0.0;
        $unidadesConstrutora = $this->unidadesConstrutora($dadosProdutos);
        $ticketMedio         = $unidadesConstrutora > 0 ? $vgvSemTerrenista / $unidadesConstrutora : 0.0;

        [$comissaoBase, $comissaoHouse, $comissaoImobiliarias] = $this->calcularComissaoBase(
            $unidadesConstrutora * $ticketMedio,
            $params
        );

        $gastosStand          = ($params['gastosMensaisStand'] ?? 0) * $vgvSemTerrenista * ($params['mesesLancamento'] ?? 0);
        $bonusCca             = ($params['bonusCca'] ?? 0) * $unidadesConstrutora;
        $bonusGerente         = $comissaoBase * ($params['bonusGerente'] ?? 0);
        $bonusGerenteRegional = $comissaoBase * ($params['bonusGerenteRegional'] ?? 0);
        $bonusCredito         = $comissaoBase * ($params['bonusCredito'] ?? 0);
        $bonusGestorComercial = $comissaoBase * ($params['bonusGestorComercial'] ?? 0);
        $ajudaGerente         = ($params['ajudaCustoGerente'] ?? 0) * ($params['mesesLancamento'] ?? 0);
        $ajudaGerenteRegional = ($params['ajudaCustoGerenteRegional'] ?? 0) * ($params['mesesLancamento'] ?? 0);
        $reembolsoLogistica   = ($params['reembolsoLogistica'] ?? 0) * ($params['mesesLancamento'] ?? 0);

        $total = ($params['standVendas'] ?? 0)
            + ($params['mobiliaDecoracao'] ?? 0)
            + $gastosStand
            + ($comissaoBase * ($params['pagamentoComissaoVenda'] ?? 0))
            + ($comissaoBase * ($params['pagamentoComissaoDesligamento'] ?? 0))
            + $bonusCca + $bonusGerente + $bonusGerenteRegional
            + $bonusCredito + $bonusGestorComercial
            + $ajudaGerente + $ajudaGerenteRegional + $reembolsoLogistica;

        return [
            'total'    => $total,
            'detalhes' => [
                'stand_vendas'              => round($params['standVendas'] ?? 0, 2),
                'mobilia_decoracao'         => round($params['mobiliaDecoracao'] ?? 0, 2),
                'gastos_mensais_stand'      => round($gastosStand, 2),
                'comissao_house'            => round($comissaoHouse, 2),
                'comissao_imobiliarias'     => round($comissaoImobiliarias, 2),
                'bonus_cca'                 => round($bonusCca, 2),
                'bonus_gerente'             => round($bonusGerente, 2),
                'bonus_gerente_regional'    => round($bonusGerenteRegional, 2),
                'bonus_credito'             => round($bonusCredito, 2),
                'bonus_gestor_comercial'    => round($bonusGestorComercial, 2),
                'ajuda_custo_gerente'       => round($ajudaGerente, 2),
                'ajuda_custo_gerente_regional' => round($ajudaGerenteRegional, 2),
                'reembolso_logistica'       => round($reembolsoLogistica, 2),
            ],
        ];
    }

    private function calcularMarketingDetalhado(array $dadosProdutos, array $params): float
    {
        $base = ($dadosProdutos['vgvSemValorTerrenista'] ?? 0) * ($params['percentualMarketing'] ?? 0);
        $fatorLancamento = $params['marketingLancamento'] ?? 0;
        if ($fatorLancamento <= 0 || $fatorLancamento >= 1) {
            return $base;
        }
        $lancamento = $base * $fatorLancamento;
        $distribuido = $base * (1 - $fatorLancamento);

        return $lancamento + $distribuido;
    }

    private function calcularSegurosPorTipologia(array $dadosProdutos, array $params): float
    {
        $total = 0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            $base = $this->ehProdutoLote($produto)
                ? $this->calcularBaseSemTerrenistaProduto($produto)
                : ($produto['vgv_produto'] ?? 0);
            $total += $base * ($params['percentualSeguros'] ?? 0);
        }

        return $total;
    }

    private function calcularItbiPorTipologia(array $dadosProdutos, array $params): float
    {
        $total = 0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            if ($this->ehProdutoLote($produto)) {
                continue;
            }
            $total += ($produto['vgv_produto'] ?? 0) * ($params['custoItbiIptu'] ?? 0);
        }

        return $total;
    }

    private function calcularRegistroPorTipologia(array $dadosProdutos, array $params): float
    {
        $unidades = 0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            if ($this->ehProdutoLote($produto)) {
                continue;
            }
            $unidades += max(0, ($produto['quantidade_unidades'] ?? 0) - ($produto['permutas'] ?? 0));
        }

        return $unidades * ($params['custoRegistro'] ?? 0);
    }

    private function calcularTxMedicao(array $dadosProdutos, array $params): float
    {
        $unidades = 0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            if ($this->ehProdutoLote($produto)) {
                continue;
            }
            $unidades += max(0, ($produto['quantidade_unidades'] ?? 0) - ($produto['permutas'] ?? 0));
        }

        return $unidades * ($params['custoMedicaoContratacao'] ?? 0);
    }

    private function calcularContratosCef(array $dadosProdutos, array $params): float
    {
        $unidades = 0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            if ($this->ehProdutoLote($produto)) {
                continue;
            }
            $unidades += max(0, ($produto['quantidade_unidades'] ?? 0) - ($produto['permutas'] ?? 0));
        }

        return $unidades * ($params['custoContratosCef'] ?? 0);
    }

    private function calcularProdutosCefPorTipologia(array $dadosProdutos, array $params): float
    {
        $total = 0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            if ($this->ehProdutoLote($produto)) {
                continue;
            }
            $total += $this->calcularBaseSemTerrenistaProduto($produto) * ($params['percentualProdutosCef'] ?? 0);
        }

        return $total;
    }

    private function ehProdutoLote(array $produto): bool
    {
        $nome = strtolower($produto['nome'] ?? '');
        return str_contains($nome, 'lote') || str_contains($nome, 'terreno');
    }

    private function calcularBaseSemTerrenistaProduto(array $produto): float
    {
        $vgvProduto = $produto['vgv_produto'] ?? 0;
        $valorTerrenista = ($produto['quantidade_unidades'] ?? 0) * ($produto['pgto_por_lote'] ?? 0);

        return max(0, $vgvProduto - $valorTerrenista);
    }

    private function buscarTerreno(int $terrenoId): Terreno
    {
        return Terreno::select(['id', 'nome', 'area_calculada', 'data_contrato'])
            ->with([
                'terrenoProdutos' => fn($q) => $q->select(['terreno_id', 'produto_id', 'unidades', 'valor', 'permuta', 'id', 'pgto_por_lote']),
                'terrenoProdutos.produto' => fn($q) => $q->select([
                    'id',
                    'name',
                    'private_area',
                    'm2_cost',
                    'infra_cost',
                    'sinal',
                    'parcela_obra',
                    'parcela_posChave',
                    'qtde_parcelas_posChave',
                    'juros_mensalSinal',
                    'juros_mensalObra',
                    'juros_mensalPosChave',
                    'correcao_anualSinal',
                    'correcao_anualObra',
                    'correcao_anualPosChave',
                    'imposto_outros',
                    'imposto_tributos',
                    'imposto_iss',
                    'demanda_minCef',
                    'curva_vendas',
                    'curva_obra',
                    'avaliacao_lotesCef',
                ]),
            ])
            ->findOrFail($terrenoId);
    }

    private function buscarViabilidade(int $terrenoId, $viabilidadeRef): Viabilidade
    {
        if ($viabilidadeRef instanceof Viabilidade) {
            return $viabilidadeRef;
        }
        if (is_numeric($viabilidadeRef)) {
            return Viabilidade::findOrFail($viabilidadeRef);
        }

        return Viabilidade::where('terreno_id', $terrenoId)->latest()->first()
            ?? new Viabilidade(['terreno_id' => $terrenoId]);
    }

    private function montarParametros(?Viabilidade $v): array
    {
        $d = config('viabilidade.defaults');
        $p = config('viabilidade.prazos');

        return [
            'percentualImpostos' => (($v->pis_cofins ?? $d['pis_cofins']) + ($v->iss ?? $d['iss']) + ($v->outros_impostos ?? $d['outros_impostos'])) / 100,
            'percentualPisCofins' => ($v->pis_cofins ?? $d['pis_cofins']) / 100,
            'percentualIss' => ($v->iss ?? $d['iss']) / 100,
            'percentualOutrosImpostos' => ($v->outros_impostos ?? $d['outros_impostos']) / 100,
            'percentualComissao' => ($v->comissao ?? $d['comissao']) / 100,
            'parceriaVgv' => ($v->parceria_vgv ?? $d['parceria_vgv']) / 100,
            'infraNaoIncidente' => ($v->infra_nao_incidente ?? $d['infra_nao_incidente']) / 100,
            'percentualIncorporacao' => ($v->incorporacao ?? $d['incorporacao']) / 100,
            'incorporacaoRi' => ($v->incorporacao_ri ?? $d['incorporacao_ri']) / 100,
            'incorporacaoEntrega' => ($v->incorporacao_entrega ?? $d['incorporacao_entrega']) / 100,
            'incorporacaoAteLancamento' => ($v->incorporacao_ate_lancamento ?? $d['incorporacao_ate_lancamento']) / 100,
            'custoAreaComum' => $v->area_comum ?? $d['area_comum'],
            'percentualContrapartidas' => ($v->contrapartidas ?? $d['contrapartidas']) / 100,
            'canteiroMensal' => $v->canteiro_mensal ?? $d['canteiro_mensal'],
            'moAdministrativa' => $v->mo_administrativa ?? $d['mo_administrativa'],
            'percentualSeguros' => ($v->seguros ?? $d['seguros']) / 100,
            'percentualAssistenciaTecnica' => ($v->assistencia_tecnica ?? $d['assistencia_tecnica']) / 100,
            'assistenciaTecnicaCurva' => $v->assistencia_tecnica_curva ?? $d['assistencia_tecnica_curva'],
            'percentualDespesasComerciais' => ($v->despesas_comerciais ?? $d['despesas_comerciais']) / 100,
            'standVendas' => $v->stand_vendas ?? $d['stand_vendas'],
            'mobiliaDecoracao' => $v->mobilia_decoracao ?? $d['mobilia_decoracao'],
            'gastosMensaisStand' => ($v->gastos_mensais_stand ?? $d['gastos_mensais_stand']) / 100,
            'comissaoHousePercentual' => ($v->comissao_house_percentual ?? $d['comissao_house_percentual']) / 100,
            'comissaoImobiliariasPercentual' => ($v->comissao_imobiliarias_percentual ?? $d['comissao_imobiliarias_percentual']) / 100,
            'percentualVendasHouse' => ($v->percentual_vendas_house ?? $d['percentual_vendas_house']) / 100,
            'ajudaCustoGerente' => $v->ajuda_custo_gerente ?? $d['ajuda_custo_gerente'],
            'ajudaCustoGerenteRegional' => $v->ajuda_custo_gerente_regional ?? $d['ajuda_custo_gerente_regional'],
            'reembolsoLogistica' => $v->reembolso_logistica ?? $d['reembolso_logistica'],
            'bonusCca' => $v->bonus_cca ?? $d['bonus_cca'],
            'bonusGerente' => ($v->bonus_gerente ?? $d['bonus_gerente']) / 100,
            'bonusGerenteRegional' => ($v->bonus_gerente_regional ?? $d['bonus_gerente_regional']) / 100,
            'bonusCredito' => ($v->bonus_credito ?? $d['bonus_credito']) / 100,
            'bonusGestorComercial' => ($v->bonus_gestor_comercial ?? $d['bonus_gestor_comercial']) / 100,
            'pagamentoComissaoVenda' => ($v->pagamento_comissao_venda ?? $d['pagamento_comissao_venda']) / 100,
            'pagamentoComissaoDesligamento' => ($v->pagamento_comissao_desligamento ?? $d['pagamento_comissao_desligamento']) / 100,
            'parcelamentoComissaoMeses' => (int) ($v->parcelamento_comissao_meses ?? $d['parcelamento_comissao_meses']),
            'percentualMarketing' => ($v->marketing ?? $d['marketing']) / 100,
            'marketingLancamento' => ($v->marketing_lancamento ?? $d['marketing_lancamento']) / 100,
            'marketingInicioAntesLancamento' => (int) ($v->marketing_inicio_antes_lancamento ?? $d['marketing_inicio_antes_lancamento']),
            'custoItbiIptu' => ($v->itbi_iptu ?? $d['itbi_iptu']) / 100,
            'custoRegistro' => $v->registro ?? $d['registro'],
            'custoMedicaoContratacao' => $v->medicao_contratacao ?? $d['medicao_contratacao'],
            'custoContratosCef' => $v->contratos_cef ?? $d['contratos_cef'],
            'percentualProdutosCef' => ($v->produtos_cef ?? $d['produtos_cef']) / 100,
            'percentualOutrasDespesasFinanceiras' => ($v->outras_despesas_financeiras ?? $d['outras_despesas_financeiras']) / 100,
            'mesesObra' => (int) ($v->prazo_obra ?? $d['prazo_obra']),
            'mesesIncorporacao' => (int) ($v->prazo_incorporacao ?? $p['meses_incorporacao']),
            'mesesLancamento' => (int) ($v->prazo_lancamento ?? $p['meses_lancamento']),
            'mesesEntrega' => $p['meses_entrega'],
            'mesesPosObra' => $p['meses_pos_obra'],
            'variavelCorrecao' => $p['variavel_correcao'],
            'compraTerreno' => $v->compra_terreno ?? 0,
            'taxaJurosPj' => ($v->taxa_juros_pj ?? $d['taxa_juros_pj']) / 100,
            'percentualAntecipacaoPj' => ($v->percentual_antecipacao_pj ?? $d['percentual_antecipacao_pj']) / 100,
            'carenciaPjMeses' => (int) ($v->carencia_pj_meses ?? $d['carencia_pj_meses']),
            'amortizacaoPjParcelas' => (int) ($v->amortizacao_pj_parcelas ?? $d['amortizacao_pj_parcelas']),
            'aporteAdicionalMensal' => $v->aporte_adicional_mensal ?? $d['aporte_adicional_mensal'],
            'devolucaoAportePercentual' => ($v->devolucao_aporte_percentual ?? $d['devolucao_aporte_percentual']) / 100,
            'distribuicaoLucrosPercentualObra' => ($v->distribuicao_lucros_percentual_obra ?? $d['distribuicao_lucros_percentual_obra']) / 100,
            'taxaExposicaoAplicada' => ($v->taxa_exposicao_aplicada ?? $d['taxa_exposicao_aplicada']) / 100,
        ];
    }

    private function processarProdutos(Terreno $terreno, array $params, ?array $customProdutos): array
    {
        $dados = [
            'vgv' => 0,
            'areaConstruida' => 0,
            'custoObraHabitacao' => 0,
            'custoInfraestrutura' => 0,
            'totalUnidades' => 0,
            'permutas' => 0,
            'dataInicio' => null,
            'produtos' => [],
            'vgvSemUnidPermutas' => 0,
            'vgvSemValorTerrenista' => 0,
            'correcaoSobreVgv' => 0,
            'vgvComCorrecao' => 0,
            'custoNaoIncidente' => 0,
            'totalUnidadesConstrutora' => 0,
            'imposto_pis' => 0,
            'imposto_cofins' => 0,
            'imposto_iss' => 0,
            'irrpj' => 0,
            'csll' => 0,
        ];

        $customMap = [];
        if ($customProdutos) {
            foreach ($customProdutos as $cp) {
                if (isset($cp['id'])) {
                    $customMap[$cp['id']] = $cp;
                }
            }
        }

        foreach ($terreno->terrenoProdutos as $terrenoProduto) {
            if (! $terrenoProduto?->produto) {
                continue;
            }

            $produto = $terrenoProduto->produto;
            $cp = $customMap[$terrenoProduto->id] ?? [];

            $unidades = $cp['unidades'] ?? $terrenoProduto->unidades ?? 1;
            $valor = $cp['valor'] ?? $terrenoProduto->valor ?? 0;
            $permutas = $cp['permuta'] ?? $terrenoProduto->permuta ?? 0;
            $pgtoPorLote = $cp['pgto_por_lote'] ?? $terrenoProduto->pgto_por_lote ?? 0;

            // Dados já vêm do produto (curvas e avaliação preenchidos no CRUD)
            $avaliacaoLotesCef = $produto->avaliacao_lotesCef ?? 0;
            $custoM2 = $cp['custo_m2'] ?? $produto->m2_cost ?? 0;
            $custoInfra = $cp['custo_infra'] ?? $produto->infra_cost ?? 0;
            $areaPrivativa = $produto->private_area ?? 0;
            $demandaMinCef = $produto->demanda_minCef ?? 0;

            $dados['totalUnidades'] += $unidades;
            $dados['permutas'] += $permutas;
            $dados['totalUnidadesConstrutora'] += ($unidades - $permutas);

            $vgvProduto = $valor * $unidades;
            $dados['vgv'] += $vgvProduto;
            $dados['areaConstruida'] += $areaPrivativa * $unidades;

            $vgvSemPermuta = $vgvProduto - ($permutas * $valor);
            $vgvSemTerrenista = $vgvSemPermuta - ($unidades * $pgtoPorLote);
            $dados['vgvSemUnidPermutas'] += $vgvSemPermuta;
            $dados['vgvSemValorTerrenista'] += $vgvSemTerrenista;

            // Impostos do produto
            $imps = $this->impostosService->calcularImpostosProduto(
                $vgvSemTerrenista,
                $produto->imposto_tributos ?? 0,
                $produto->imposto_iss ?? 0,
                $produto->imposto_outros ?? 0
            );
            $dados['imposto_pis'] += $imps['imposto_pis'];
            $dados['imposto_cofins'] += $imps['imposto_cofins'];
            $dados['imposto_iss'] += $imps['imposto_iss'];
            $dados['irrpj'] += $imps['irrpj'];
            $dados['csll'] += $imps['csll'];

            $dados['custoObraHabitacao'] += $custoM2 * $areaPrivativa * ($unidades - $permutas);
            $dados['custoInfraestrutura'] += $custoInfra * ($unidades - $permutas);

            $dados['produtos'][] = [
                'id' => $produto->id,
                'terreno_produto_id' => $terrenoProduto->id,
                'nome' => $produto->name,
                'preco' => $valor,
                'metragem' => $areaPrivativa,
                'quantidade_unidades' => $unidades,
                'custo_m2' => $custoM2,
                'custo_infraestrutura' => $custoInfra,
                'vgv_produto' => $vgvProduto,
                'avaliacao_lotesCef' => $avaliacaoLotesCef,
                'permutas' => $permutas,
                'pgto_por_lote' => $pgtoPorLote,
                'demanda_minCef' => $demandaMinCef,
                'curva_vendas' => $produto->curva_vendas ?? [],
                'curva_obra' => $produto->curva_obra ?? [],
                'imposto_outros' => ($produto->imposto_outros ?? 0) / 100,
                'financeiro' => [
                    'sinal' => $produto->sinal ?? 0,
                    'parcela_obra' => $produto->parcela_obra ?? 0,
                    'parcela_posChave' => $produto->parcela_posChave ?? 0,
                    'qtde_parcelas_posChave' => (int) ($produto->qtde_parcelas_posChave ?? 0),
                    'juros_mensalSinal' => $produto->juros_mensalSinal ?? 0,
                    'juros_mensalObra' => $produto->juros_mensalObra ?? 0,
                    'juros_mensalPosChave' => $produto->juros_mensalPosChave ?? 0,
                    'correcao_anualSinal' => $produto->correcao_anualSinal ?? 0,
                    'correcao_anualObra' => $produto->correcao_anualObra ?? 0,
                    'correcao_anualPosChave' => $produto->correcao_anualPosChave ?? 0,
                    'imposto_pis' => $imps['imposto_pis'],
                    'imposto_cofins' => $imps['imposto_cofins'],
                    'imposto_iss' => $imps['imposto_iss'],
                    'outras_deducoes' => $imps['outras_deducoes'],
                    'irrpj' => $imps['irrpj'],
                    'csll' => $imps['csll'],
                ],
            ];

            $dados['custoCasaM2'] = $custoM2 * $areaPrivativa;
            $dados['custoInfraM2'] = $custoInfra;
        }

        $dados['correcaoSobreVgv'] = $dados['vgvSemValorTerrenista'] * $params['variavelCorrecao'];
        $dados['vgvComCorrecao'] = $dados['vgvSemValorTerrenista'] + $dados['correcaoSobreVgv'];
        $dados['custoNaoIncidente'] = $params['infraNaoIncidente'] * $dados['vgv'];
        $dados['dataInicio'] = Carbon::now()->addYears(2);

        return $dados;
    }

    private function calcularPeriodos(Carbon $dataInicio, array $params): array
    {
        $dataLancamento = $dataInicio->copy();
        $inicioIncorporacao = $dataLancamento->copy()->subMonths($params['mesesIncorporacao']);
        $fimLancamento = $dataLancamento->copy()->addMonths($params['mesesLancamento'] - 1);
        $inicioObra = $fimLancamento->copy()->addMonth();
        $fimObra = $inicioObra->copy()->addMonths($params['mesesObra'] - 1);
        $dataEntrega = $fimObra->copy()->addMonth();
        $inicioPos = $dataEntrega->copy()->addMonth();
        $fimPos = $inicioPos->copy()->addMonths($params['mesesPosObra'] - 1);

        return compact('inicioIncorporacao', 'dataLancamento', 'fimLancamento', 'inicioObra', 'fimObra', 'dataEntrega', 'inicioPos', 'fimPos');
    }

    private function identificarPeriodo(Carbon $data, array $datas): string
    {
        if ($data < $datas['dataLancamento']) {
            return 'Incorporação';
        }
        if ($data->between($datas['dataLancamento'], $datas['fimLancamento'])) {
            return 'Lançamento';
        }
        if ($data->between($datas['inicioObra'], $datas['fimObra'])) {
            return 'Obra';
        }
        if ($data->format('Y-m') === $datas['dataEntrega']->format('Y-m')) {
            return 'Entrega';
        }
        if ($data >= $datas['inicioPos']) {
            return 'Pós-Obra';
        }

        return 'Transição';
    }

    /**
     * Pré-calcula recursos próprios baseado na lógica de calculateMonthlyReceipts
     *
     * - Sinal: dividido em parcelas durante o lançamento (se venda no lançamento)
     * - Obra: parcelas com correção composta pelo tempo desde a venda
     * - Pós-chave: amortização + juros + correção sobre saldo devedor decrescente
     */
    private function preCalcularRecursosProprios(array $produtos, array $datas, array $params, ViabilidadeFluxoContext $ctx): void
    {
        $ctx->recursosProprios = [];

        $dataLancamento = $datas['dataLancamento'];
        $fimLancamento = $datas['fimLancamento'];
        $inicioObra = $datas['inicioObra'];
        $fimObra = $datas['fimObra'];
        $dataEntrega = $datas['dataEntrega'];

        $prazoLancamento = $params['mesesLancamento'];
        $prazoObra = $params['mesesObra'];
        $prazoPosChave = 36; // Padrão 36 parcelas pós-chave

        // Taxa de correção anual para mensal (composição)
        $taxaCorrecaoObraAnual = 0.05; // 5% ao ano
        $taxaCorrecaoPosAnual = 0.045; // 4.5% ao ano
        $r_obra = pow(1 + $taxaCorrecaoObraAnual, 1 / 12.0) - 1;
        $r_pos = pow(1 + $taxaCorrecaoPosAnual, 1 / 12.0) - 1;
        $juros_pos = 0.01; // 1% ao mês

        foreach ($produtos as $produto) {
            $curvaVendas = $this->curvaService->extrairCurva($produto['curva_vendas'] ?? null);
            $curvaVendas = $this->curvaService->normalizarCurva($curvaVendas);

            $unidadesProduto = $produto['quantidade_unidades'] ?? 1;
            $precoProduto = $produto['preco'] ?? 0;
            $fin = $produto['financeiro'];

            // Percentuais do produto
            $percentualSinal = ($fin['sinal'] ?? 2) / 100;
            $percentualObra = ($fin['parcela_obra'] ?? 9) / 100;
            $percentualPos = ($fin['parcela_posChave'] ?? 9) / 100;
            $qtdParcelasPos = max(1, (int) ($fin['qtde_parcelas_posChave'] ?? $prazoPosChave));

            // Cálculo do fim da obra em índice de meses
            $endObra = $prazoLancamento + $prazoObra;
            $mesEntrega = $endObra + 1;

            // Para cada mês de venda (coorte)
            foreach ($curvaVendas as $mesVenda => $percentualVenda) {
                if ($percentualVenda <= 0) {
                    continue;
                }

                $s = $mesVenda + 1; // Índice 1-based como na função original

                // Unidades vendidas neste mês
                $unidadesVendidas = $unidadesProduto * $percentualVenda / 100;

                // Valores nominais
                $valorSinal = $precoProduto * $percentualSinal;
                $valorObra = $precoProduto * $percentualObra;
                $valorPos = $precoProduto * $percentualPos;

                // ═══════════════════════════════════════════════════════════════
                // SINAL: Divide em parcelas durante o lançamento (se venda <= lançamento)
                // ═══════════════════════════════════════════════════════════════
                if ($s <= $prazoLancamento) {
                    // Quantidade de parcelas = meses restantes até fim do lançamento
                    $numSinal = $prazoLancamento - $s + 1;
                    $parcelaSinal = $valorSinal / $numSinal;

                    for ($i = 0; $i < $numSinal; $i++) {
                        $mesRecebimento = $s + $i;
                        $dataRecebimento = $dataLancamento->copy()->addMonths($mesRecebimento - 1);
                        $chaveMes = $dataRecebimento->format('Y-m');

                        $ctx->recursosProprios[$chaveMes]['sinal'] =
                            ($ctx->recursosProprios[$chaveMes]['sinal'] ?? 0) + ($parcelaSinal * $unidadesVendidas);
                    }
                } else {
                    // Venda após lançamento: sinal recebido no mês da venda
                    $dataRecebimento = $dataLancamento->copy()->addMonths($s - 1);
                    $chaveMes = $dataRecebimento->format('Y-m');

                    $ctx->recursosProprios[$chaveMes]['sinal'] =
                        ($ctx->recursosProprios[$chaveMes]['sinal'] ?? 0) + ($valorSinal * $unidadesVendidas);
                }

                // ═══════════════════════════════════════════════════════════════
                // OBRA: Parcelas com correção composta pelo tempo desde a venda
                // ═══════════════════════════════════════════════════════════════
                $inicioObraCoorte = max($s, $prazoLancamento + 1);
                $numObraParcelas = $endObra - $inicioObraCoorte + 1;

                if ($numObraParcelas > 0 && $valorObra > 0) {
                    $parcelaObraNominal = $valorObra / $numObraParcelas;

                    for ($i = 0; $i < $numObraParcelas; $i++) {
                        $mesRecebimento = $inicioObraCoorte + $i;
                        $mesesPassados = $mesRecebimento - $s;

                        // Correção composta pelo tempo desde a venda
                        $parcelaAjustada = $parcelaObraNominal * pow(1 + $r_obra, $mesesPassados);
                        $correcaoMes = $parcelaAjustada - $parcelaObraNominal;

                        $dataRecebimento = $dataLancamento->copy()->addMonths($mesRecebimento - 1);
                        $chaveMes = $dataRecebimento->format('Y-m');

                        $ctx->recursosProprios[$chaveMes]['parcelas_obra'] =
                            ($ctx->recursosProprios[$chaveMes]['parcelas_obra'] ?? 0) + ($parcelaAjustada * $unidadesVendidas);
                        $ctx->recursosProprios[$chaveMes]['correcao'] =
                            ($ctx->recursosProprios[$chaveMes]['correcao'] ?? 0) + ($correcaoMes * $unidadesVendidas);
                    }
                }
            }

            // ═══════════════════════════════════════════════════════════════
            // PÓS-CHAVE: Agregado, com amortização + juros + correção sobre saldo devedor
            // ═══════════════════════════════════════════════════════════════
            $valorPosTotal = $precoProduto * $percentualPos * $unidadesProduto;
            $amortizacao = $valorPosTotal / $qtdParcelasPos;

            for ($k = 1; $k <= $qtdParcelasPos; $k++) {
                // Saldo devedor decrescente
                $saldoDevedor = $valorPosTotal - ($amortizacao * ($k - 1));

                $jurosMes = $saldoDevedor * $juros_pos;
                $correcaoMes = $saldoDevedor * $r_pos;
                $pagamentoMes = $amortizacao + $jurosMes + $correcaoMes;

                $dataRecebimento = $dataEntrega->copy()->addMonths($k - 1);
                $chaveMes = $dataRecebimento->format('Y-m');

                $ctx->recursosProprios[$chaveMes]['parcelas_pos'] =
                    ($ctx->recursosProprios[$chaveMes]['parcelas_pos'] ?? 0) + $pagamentoMes;
                $ctx->recursosProprios[$chaveMes]['juros'] =
                    ($ctx->recursosProprios[$chaveMes]['juros'] ?? 0) + $jurosMes;
                $ctx->recursosProprios[$chaveMes]['correcao'] =
                    ($ctx->recursosProprios[$chaveMes]['correcao'] ?? 0) + $correcaoMes;
            }
        }
    }

    /**
     * Inicializa caches para cálculos CEF (Recurso Terrenos e Medição de Obra)
     */
    private function inicializarCachesCef(array $dadosProdutos, array $datas, ViabilidadeFluxoContext $ctx): void
    {
        // Reset dos caches
        $ctx->vendasPorMes            = [];
        $ctx->vendasAcumuladas        = 0.0;
        $ctx->demandaAtingida         = false;
        $ctx->mesDemandaAtingida      = null;
        $ctx->medicaoObraAcumulada    = 0.0;
        $ctx->curvaObraAcumulada      = 0.0;
        $ctx->mesObraAtual            = 0;
        $ctx->valorMedicaoTotal       = 0.0;

        // Calcular demanda mínima total
        $ctx->demandaMinima = 0.0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            $ctx->demandaMinima += $produto['demanda_minCef'] ?? 0;
        }

        // Calcular valor total para medição de obra (Financiamento CEF)
        // Fórmula: (VGV × 80%) - Recurso Terrenos
        $vgv = $dadosProdutos['vgv'];
        $totalRecursoTerrenos = 0;

        foreach ($dadosProdutos['produtos'] as $produto) {
            $unidades = $produto['quantidade_unidades'] ?? 0;
            $preco = $produto['preco'] ?? 0;
            $avaliacaoCef = $produto['avaliacao_lotesCef'] ?? 0;

            // Calcular Recurso Terrenos (pode ser percentual ou absoluto)
            if ($avaliacaoCef > 0 && $avaliacaoCef <= 1) {
                $totalRecursoTerrenos += $avaliacaoCef * $preco * $unidades;
            } else {
                $totalRecursoTerrenos += $avaliacaoCef * $unidades;
            }
        }

        // Valor Financiamento = (VGV × 80%) - Recurso Terrenos
        $ctx->valorMedicaoTotal = max(0, ($vgv * self::PERCENTUAL_FINANCIAMENTO_CEF) - $totalRecursoTerrenos);

        // Pré-calcular vendas por mês (unidades vendidas)
        $dataLancamento = $datas['dataLancamento'];

        foreach ($dadosProdutos['produtos'] as $produto) {
            $curvaVendas = $this->curvaService->extrairCurva($produto['curva_vendas'] ?? null);
            $curvaVendas = $this->curvaService->normalizarCurva($curvaVendas);
            $unidadesProduto = $produto['quantidade_unidades'] ?? 0;

            foreach ($curvaVendas as $mesIndex => $percentualVenda) {
                if ($percentualVenda <= 0) {
                    continue;
                }

                $dataVenda = $dataLancamento->copy()->addMonths($mesIndex);
                $chaveMes = $dataVenda->format('Y-m');

                $unidadesVendidas = $unidadesProduto * ($percentualVenda / 100);
                $ctx->vendasPorMes[$chaveMes] = ($ctx->vendasPorMes[$chaveMes] ?? 0) + $unidadesVendidas;
            }
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Converte string "YYYY-MM" para Carbon (início do mês). */
    private function parseMes(string $mes): Carbon
    {
        return Carbon::parse($mes . '-01');
    }

    /** Custo total de obra: habitação + infraestrutura + nãoIncidente. */
    private function custoObraTotal(array $d): float
    {
        return ($d['custoObraHabitacao'] ?? 0.0) + ($d['custoInfraestrutura'] ?? 0.0) + ($d['custoNaoIncidente'] ?? 0.0);
    }

    /** Unidades que pertencem à construtora (excluindo permutas). */
    private function unidadesConstrutora(array $d): int
    {
        return max(1, (int) ($d['totalUnidadesConstrutora'] ?? $d['totalUnidades'] ?? 1));
    }

    /**
     * Calcula comissão base, house e imobiliárias sobre um valor vendido.
     *
     * @return array{0:float,1:float,2:float} [comissaoBase, comissaoHouse, comissaoImobiliarias]
     */
    private function calcularComissaoBase(float $valorVendido, array $params): array
    {
        $percHouse        = $params['percentualVendasHouse'] ?? 0.0;
        $taxaHouse        = $params['comissaoHousePercentual'] ?? 0.0;
        $taxaImob         = $params['comissaoImobiliariasPercentual'] ?? 0.0;
        $taxaMedia        = ($percHouse * $taxaHouse) + ((1 - $percHouse) * $taxaImob);
        $comissaoBase     = $valorVendido * $taxaMedia;
        $comissaoHouse    = $valorVendido * $percHouse * $taxaHouse;
        $comissaoImob     = $valorVendido * (1 - $percHouse) * $taxaImob;

        return [$comissaoBase, $comissaoHouse, $comissaoImob];
    }

    /**
     * Calcula Recurso Terrenos (CEF)
     *
     * Lógica:
     * - Valor = unidades_vendidas × (avaliacao_cef × preco)
     * - Acumula os 4 primeiros meses de vendas
     * - Defasagem: dinheiro entra 2 meses após fim do lançamento (2º mês de obra)
     * - Mês 2 de obra: libera o acumulado dos meses 1-4
     * - Mês 3+ de obra: libera normalmente (com defasagem de 2 meses)
     */
    private function calcularRecursoTerrenos(string $mes, array $dadosProdutos, array $datas, ViabilidadeFluxoContext $ctx): array
    {
        $dataAtual = Carbon::parse($mes . '-01');
        $dataLancamento = $datas['dataLancamento']->copy()->startOfMonth();
        $fimLancamento = $datas['fimLancamento']->copy()->startOfMonth();
        $inicioObra = $datas['inicioObra']->copy()->startOfMonth();

        // Antes do 2º mês de obra, não há receita de RT (defasagem de 2 meses)
        $segundoMesObra = $inicioObra->copy()->addMonth();
        if ($dataAtual->startOfMonth() < $segundoMesObra) {
            return ['valor' => 0];
        }

        // Calcular qual mês de obra estamos (1-based, começando no inicioObra)
        $mesObraNumero = (int) $inicioObra->diffInMonths($dataAtual->copy()->startOfMonth()) + 1;

        // Mês de venda correspondente (com defasagem de 2 meses)
        // 2º mês de obra recebe o acumulado (meses vendas 1-4)
        // 3º mês de obra recebe mês vendas 5
        // 4º mês de obra recebe mês vendas 6
        // ...
        $mesVendaCorrespondente = $mesObraNumero - 2 + 4; // +4 porque no 2º mês de obra libera até mês 4

        if ($mesObraNumero == 2) {
            // 2º mês de obra: calcular acumulado dos meses 1-4 de vendas
            $valorAcumulado = 0;
            for ($mesVenda = 1; $mesVenda <= 4; $mesVenda++) {
                $valorAcumulado += $this->calcularRtMesVenda($mesVenda, $dadosProdutos);
            }

            return ['valor' => round($valorAcumulado, 2)];
        } else {
            // 3º mês de obra em diante: calcular valor do mês de venda correspondente
            // mesObraNumero = 3 → mesVenda = 5
            // mesObraNumero = 4 → mesVenda = 6
            $mesVendaCorrespondente = $mesObraNumero + 2;
            $valorRtMes = $this->calcularRtMesVenda($mesVendaCorrespondente, $dadosProdutos);

            return ['valor' => round($valorRtMes, 2)];
        }
    }

    /**
     * Calcula o valor de RT para um mês de venda específico
     */
    private function calcularRtMesVenda(int $mesVenda, array $dadosProdutos): float
    {
        $valorRtMes = 0;

        foreach ($dadosProdutos['produtos'] as $produto) {
            $curvaVendas = $this->curvaService->extrairCurva($produto['curva_vendas'] ?? null);
            $curvaVendas = $this->curvaService->normalizarCurva($curvaVendas);

            if (empty($curvaVendas)) {
                continue;
            }

            $indiceMes = $mesVenda - 1;
            $percVendasMes = $curvaVendas[$indiceMes] ?? 0;

            $unidadesProduto = $produto['quantidade_unidades'] ?? 0;
            $unidadesVendidasMes = $unidadesProduto * ($percVendasMes / 100);

            $avaliacaoCef = $produto['avaliacao_lotesCef'] ?? 0;
            $preco = $produto['preco'] ?? 0;

            if ($avaliacaoCef > 0 && $avaliacaoCef <= 1) {
                $valorPorUnidade = $avaliacaoCef * $preco;
            } else {
                $valorPorUnidade = $avaliacaoCef;
            }

            $valorRtMes += $unidadesVendidasMes * $valorPorUnidade;
        }

        return $valorRtMes;
    }

    /**
     * Calcula Medição de Obra (CEF)
     *
     * Lógica:
     * 1. Acumular percentual da Curva de Obra no mês
     * 2. Calcular Medição Teórica = %ObraAcumulada × ValorTotalFinanciado
     * 3. Calcular Medição Vendida = Medição Teórica × %VendasAcumulada
     * 4. Valor Recebido no Mês = Medição Vendida Atual - Medição Vendida Anterior
     */
    private function calcularMedicaoObra(string $mes, array $dadosProdutos, array $datas, array $params, ViabilidadeFluxoContext $ctx): array
    {
        $dataAtual = Carbon::parse($mes . '-01');
        $inicioObra = $datas['inicioObra']->copy()->startOfMonth();
        $fimObra = $datas['fimObra']->copy()->startOfMonth();

        // Só calcula durante o período de obra
        // Usa startOfMonth para comparação segura
        if ($dataAtual->startOfMonth() < $inicioObra || $dataAtual->startOfMonth() > $fimObra) {
            return ['valor' => 0];
        }

        // Calcular qual mês de obra estamos (1-based)
        $mesObraAtual = (int) $inicioObra->diffInMonths($dataAtual->startOfMonth()) + 1;

        // Obter percentual da curva S agregada para este mês
        $curvaObra = $dadosProdutos['curvaObraAgregada'] ?? $this->agregarCurvaObra($dadosProdutos['produtos'] ?? []);
        $indice = $mesObraAtual - 1;
        $percObraMes = $curvaObra[$indice] ?? 0.0;

        // Atualizar acumulado de obra apenas se for um novo mês de obra (proteção contra chamadas repetidas, embora o fluxo seja sequencial)
        // No contexto atual, como é sequencial e mesObraAtual começa em 0, podemos só comparar
        if ($mesObraAtual > $ctx->mesObraAtual) {
            $ctx->curvaObraAcumulada += ($percObraMes / 100);
            $ctx->mesObraAtual = $mesObraAtual;
        }

        // 1. Valor da Medição Teórica Acumulada (Física)
        $medicaoTeoricaAcumulada = $ctx->valorMedicaoTotal * $ctx->curvaObraAcumulada;

        // 2. Percentual de Vendas Acumulado
        $totalUnidades = $dadosProdutos['totalUnidades'];
        $percVendasAcumulado = $totalUnidades > 0 ? $ctx->vendasAcumuladas / $totalUnidades : 0;

        // 3. Valor da Medição Vendida Acumulada (Financeira)
        $medicaoVendidaAcumulada = $medicaoTeoricaAcumulada * $percVendasAcumulado;

        // 4. Valor a Receber no Mês (Diferença do acumulado anterior)
        // medicaoObraAcumulada armazena o "Total Recebido Até Agora"
        $valorReceberMes = max(0, $medicaoVendidaAcumulada - $ctx->medicaoObraAcumulada);

        // Atualizar o acumulado recebido
        $ctx->medicaoObraAcumulada += $valorReceberMes;

        return ['valor' => round($valorReceberMes, 2)];
    }

    private function calcularCustosDiretos(string $mes, string $periodo, array $datas, array $params, float $vgv, float $custoObraTotal, array $dadosProdutos): array
    {
        $custos = [];
        $dataAtual = Carbon::parse($mes . '-01');

        $custoIncorp = $vgv * $params['percentualIncorporacao'];
        $areaComumTotal = $params['custoAreaComum'] * ($dadosProdutos['totalUnidades'] ?? 0);
        $mesesIncorporacao = max(1, (int) $params['mesesIncorporacao']);
        $mesesPosLancamento = max(1, (int) ($params['mesesLancamento'] + $params['mesesObra']));

        if ($dataAtual->between($datas['inicioIncorporacao'], $datas['dataLancamento'])) {
            $custos['Incorporação Até Lançamento'] = round(($custoIncorp * $params['incorporacaoAteLancamento']) / $mesesIncorporacao, 2);
        }
        if ($dataAtual->between($datas['dataLancamento'], $datas['fimObra'])) {
            $custos['Incorporação Pós Lançamento'] = round(($custoIncorp * (1 - $params['incorporacaoAteLancamento'])) / $mesesPosLancamento, 2);
        }

        if ($periodo === 'Obra') {
            $mesObraIndex = (int) $datas['inicioObra']->diffInMonths($dataAtual) + 1;
            $curvaObra = $dadosProdutos['curvaObraAgregada'] ?? $this->agregarCurvaObra($dadosProdutos['produtos'] ?? []);
            $percentualMes = $curvaObra[$mesObraIndex - 1] ?? 0.0;
            $custos['Obra'] = round($custoObraTotal * ($percentualMes / 100), 2);
            $custos['Canteiro'] = round($params['canteiroMensal'], 2);
            $custos['Área Comum'] = round($areaComumTotal / max(1, $params['mesesObra']), 2);
            $custos['M.O. Administrativa'] = round($params['moAdministrativa'], 2);

            if ($mes === $datas['inicioObra']->format('Y-m')) {
                $custos['Medição/Contratação'] = $this->calcularTxMedicao($dadosProdutos, $params);
                $custos['Contratos CEF'] = $this->calcularContratosCef($dadosProdutos, $params);
            }
        }

        $seguroMensal = $this->calcularSegurosMensal($mes, $dadosProdutos, $datas, $params);
        if ($seguroMensal > 0) {
            $custos['Seguros'] = round($seguroMensal, 2);
        }

        if ($periodo === 'Pós-Obra') {
            $baseAssistencia = $custoObraTotal + ($vgv * $params['percentualContrapartidas']) + $areaComumTotal;
            $custos['Assistência Técnica'] = round($this->calcularAssistenciaTecnicaMensal($mes, $datas, $params, $baseAssistencia), 2);
        }

        if ($periodo === 'Entrega') {
        }

        return [
            'detalhes' => $custos,
            'total' => array_sum($custos),
        ];
    }

    private function calcularCustosOperacionais(string $mes, array $dadosProdutos, array $datas, array $params, ViabilidadeFluxoContext $ctx): array
    {
        $despesasComerciais = $this->calcularDespesasComerciaisMensais($mes, $dadosProdutos, $datas, $params, $ctx);
        $marketing = $this->calcularMarketingMensal($mes, $dadosProdutos, $datas, $params, $ctx);

        return [
            'total' => $despesasComerciais['total'] + $marketing['total'],
            'detalhes' => array_merge($despesasComerciais['detalhes'], ['Marketing' => $marketing['total']]),
        ];
    }

    private function calcularCustoTerreno(string $mes, float $receitaMes, array $dadosProdutos, array $params): float
    {
        $totalCustoTerreno = ($dadosProdutos['permutas'] * ($dadosProdutos['produtos'][0]['preco'] ?? 0)) + $params['compraTerreno'];
        $custoParceria = $params['parceriaVgv'] * ($dadosProdutos['vgvComCorrecao'] ?? $dadosProdutos['vgv']);
        $receitaTotal = $dadosProdutos['vgvComCorrecao'] ?? $dadosProdutos['vgv'];

        return $receitaTotal > 0 ? (($totalCustoTerreno + $custoParceria) * $receitaMes) / $receitaTotal : 0;
    }

    private function calcularDespesasComerciaisMensais(string $mes, array $dadosProdutos, array $datas, array $params, ViabilidadeFluxoContext $ctx): array
    {
        $dataAtual = Carbon::parse($mes . '-01');
        $vgvSemTerrenista = $dadosProdutos['vgvSemValorTerrenista'] ?? 0;
        $unidadesConstrutora = max(1, $dadosProdutos['totalUnidadesConstrutora'] ?? $dadosProdutos['totalUnidades'] ?? 1);
        $ticketMedio = $vgvSemTerrenista / $unidadesConstrutora;
        $unidadesVendidasMes = $ctx->vendasPorMes[$mes] ?? 0;
        $valorVendidoMes = $unidadesVendidasMes * $ticketMedio;
        $taxaComissaoMedia = (($params['percentualVendasHouse'] ?? 0) * ($params['comissaoHousePercentual'] ?? 0)) +
            ((1 - ($params['percentualVendasHouse'] ?? 0)) * ($params['comissaoImobiliariasPercentual'] ?? 0));
        $comissaoBaseMes = $valorVendidoMes * $taxaComissaoMedia;
        $comissaoVenda = $comissaoBaseMes * ($params['pagamentoComissaoVenda'] ?? 0);
        $comissaoDesligamento = $this->calcularComissaoDesligamentoMensal($mes, $ticketMedio, $params, $ctx);
        $bonusCca = ($params['bonusCca'] ?? 0) * $unidadesVendidasMes;
        $bonusGerente = $comissaoBaseMes * ($params['bonusGerente'] ?? 0);
        $bonusGerenteRegional = $comissaoBaseMes * ($params['bonusGerenteRegional'] ?? 0);
        $bonusCredito = $comissaoBaseMes * ($params['bonusCredito'] ?? 0);
        $bonusGestorComercial = $comissaoBaseMes * ($params['bonusGestorComercial'] ?? 0);
        $inicioAntesLancamento = $datas['dataLancamento']->copy()->subMonths(max(0, $params['marketingInicioAntesLancamento']));
        $standParcelado = $dataAtual->between($datas['dataLancamento'], $datas['fimLancamento']) ? (($params['standVendas'] ?? 0) / max(1, $params['mesesLancamento'])) : 0;
        $mobiliaParcelada = $dataAtual->between($inicioAntesLancamento, $datas['dataLancamento']->copy()->subMonth()) ? (($params['mobiliaDecoracao'] ?? 0) / max(1, $params['marketingInicioAntesLancamento'])) : 0;
        $gastosMensaisStand = $dataAtual->between($datas['dataLancamento'], $datas['fimObra']) ? ($vgvSemTerrenista * ($params['gastosMensaisStand'] ?? 0)) : 0;
        $ajudaGerente = $dataAtual->between($datas['dataLancamento'], $datas['fimObra']) ? ($params['ajudaCustoGerente'] ?? 0) : 0;
        $ajudaGerenteRegional = $dataAtual->between($datas['dataLancamento'], $datas['fimObra']) ? ($params['ajudaCustoGerenteRegional'] ?? 0) : 0;
        $reembolsoLogistica = $dataAtual->between($datas['dataLancamento'], $datas['fimObra']) ? ($params['reembolsoLogistica'] ?? 0) : 0;

        $total = $standParcelado + $mobiliaParcelada + $gastosMensaisStand + $comissaoVenda + $comissaoDesligamento +
            $bonusCca + $bonusGerente + $bonusGerenteRegional + $bonusCredito + $bonusGestorComercial +
            $ajudaGerente + $ajudaGerenteRegional + $reembolsoLogistica;

        return [
            'total' => $total,
            'detalhes' => [
                'Stand de Vendas' => $standParcelado,
                'Mobiliário e Decoração' => $mobiliaParcelada,
                'Gastos Mensais Stand' => $gastosMensaisStand,
                'Comissão Venda' => $comissaoVenda,
                'Comissão Desligamento' => $comissaoDesligamento,
                'Bônus CCA' => $bonusCca,
                'Bônus Gerente' => $bonusGerente,
                'Bônus Gerente Regional' => $bonusGerenteRegional,
                'Bônus Crédito' => $bonusCredito,
                'Bônus Gestor Comercial' => $bonusGestorComercial,
                'Ajuda de Custo Gerente' => $ajudaGerente,
                'Ajuda de Custo Gerente Regional' => $ajudaGerenteRegional,
                'Reembolso Logística' => $reembolsoLogistica,
            ],
        ];
    }

    private function calcularComissaoDesligamentoMensal(string $mes, float $ticketMedio, array $params, ViabilidadeFluxoContext $ctx): float
    {
        $dataAtual = Carbon::parse($mes . '-01');
        $parcelamento = max(1, (int) ($params['parcelamentoComissaoMeses'] ?? 1));
        $taxaComissaoMedia = (($params['percentualVendasHouse'] ?? 0) * ($params['comissaoHousePercentual'] ?? 0)) +
            ((1 - ($params['percentualVendasHouse'] ?? 0)) * ($params['comissaoImobiliariasPercentual'] ?? 0));
        $percentualDesligamento = $params['pagamentoComissaoDesligamento'] ?? 0;
        $totalMes = 0;

        foreach ($ctx->vendasPorMes as $mesVenda => $unidadesVendaMes) {
            if ($unidadesVendaMes <= 0 || $mesVenda >= $mes) {
                continue;
            }
            $dataVenda = Carbon::parse($mesVenda . '-01');
            $mesesDecorridos = $dataVenda->diffInMonths($dataAtual);
            if ($mesesDecorridos < 1 || $mesesDecorridos > $parcelamento) {
                continue;
            }
            $valorVendaMes = $unidadesVendaMes * $ticketMedio;
            $desligamentoTotalMesVenda = $valorVendaMes * $taxaComissaoMedia * $percentualDesligamento;
            $totalMes += $desligamentoTotalMesVenda / $parcelamento;
        }

        return $totalMes;
    }

    private function calcularMarketingMensal(string $mes, array $dadosProdutos, array $datas, array $params, ViabilidadeFluxoContext $ctx): array
    {
        $dataAtual = Carbon::parse($mes . '-01');
        $baseMarketing = ($dadosProdutos['vgvSemValorTerrenista'] ?? 0) * ($params['percentualMarketing'] ?? 0);
        $totalLancamento = $baseMarketing * ($params['marketingLancamento'] ?? 0);
        $totalVariavel = $baseMarketing - $totalLancamento;
        $marketingLancamentoMensal = $dataAtual->between($datas['dataLancamento'], $datas['fimLancamento']) ? ($totalLancamento / max(1, $params['mesesLancamento'])) : 0;
        $unidadesVendidasMes = $ctx->vendasPorMes[$mes] ?? 0;
        $totalUnidadesConstrutora = max(1, $dadosProdutos['totalUnidadesConstrutora'] ?? $dadosProdutos['totalUnidades'] ?? 1);
        $marketingVariavelMensal = $totalVariavel * ($unidadesVendidasMes / $totalUnidadesConstrutora);

        return [
            'total' => $marketingLancamentoMensal + $marketingVariavelMensal,
        ];
    }

    private function calcularSegurosMensal(string $mes, array $dadosProdutos, array $datas, array $params): float
    {
        $dataAtual = Carbon::parse($mes . '-01');
        if (! $dataAtual->between($datas['dataLancamento'], $datas['fimObra'])) {
            return 0;
        }
        $totalSeguros = $this->calcularSegurosPorTipologia($dadosProdutos, $params);
        $mesesRateio = max(1, $params['mesesLancamento'] + $params['mesesObra']);

        return $totalSeguros / $mesesRateio;
    }

    private function calcularAssistenciaTecnicaMensal(string $mes, array $datas, array $params, float $baseAssistencia): float
    {
        $dataAtual = Carbon::parse($mes . '-01');
        if (! $dataAtual->between($datas['inicioPos'], $datas['fimPos'])) {
            return 0;
        }

        $mesPosObra = (int) $datas['inicioPos']->diffInMonths($dataAtual) + 1;
        $curva = array_values($params['assistenciaTecnicaCurva'] ?? [50, 20, 10, 10, 10]);
        $indiceAno = min(count($curva) - 1, (int) floor(($mesPosObra - 1) / 12));
        $percentualAno = ($curva[$indiceAno] ?? 0) / 100;
        $totalAssistencia = $baseAssistencia * ($params['percentualAssistenciaTecnica'] ?? 0);

        return ($totalAssistencia * $percentualAno) / 12;
    }

    private function calcularIndicadoresFinanceiros(array $fluxo, array $datas, array $params, array $dadosProdutos): array
    {
        $fluxoFinanceiro = [];
        $fluxoOperacionalTir = [];
        $fluxoFinanceiroTir = [];
        $saldoOperacional = 0.0;
        $saldoFinanceiro = 0.0;
        $paybackOperacionalMes = null;
        $paybackFinanceiroMes = null;
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
            $dataAtual = Carbon::parse($mes . '-01');
            $valorOperacional = (float) ($linha['lucro'] ?? 0);
            $fluxoOperacionalTir[] = ['data' => $dataAtual->copy(), 'valor' => $valorOperacional];
            $saldoOperacional += $valorOperacional;
            if ($paybackOperacionalMes === null && $saldoOperacional >= 0) {
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

            if ($paybackFinanceiroMes === null && $saldoFinanceiro >= 0) {
                $paybackFinanceiroMes = count($fluxoFinanceiroTir) + 1;
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
        }

        return [
            $fluxoFinanceiro,
            [
                'tir_financeira' => $this->calcularTir($fluxoFinanceiroTir),
                'exposicao_maxima_financeira' => collect($fluxoFinanceiro)->min('saldo_acumulado'),
                'exposicao_maxima_operacional' => collect($fluxo)->min('saldo_acumulado'),
                'payback_operacional_meses' => $paybackOperacionalMes,
                'payback_financeiro_meses' => $paybackFinanceiroMes,
                'exposicao_aplicada_total' => round($exposicaoAplicadaTotal, 2),
            ],
        ];
    }

    private function calcularIndicadoresVso(array $fluxo, array $dadosProdutos): array
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

    private function calcularDreContabilPoc(array $fluxo, array $dre, array $dadosProdutos): array
    {
        $custoOrcadoObra = ($dadosProdutos['custoObraHabitacao'] ?? 0) + ($dadosProdutos['custoInfraestrutura'] ?? 0);
        $custoIncorridoObra = 0.0;
        $custoIncorridoTotal = 0.0;
        foreach ($fluxo as $linha) {
            $despesas = $linha['despesas'] ?? [];
            $custoIncorridoObra += (float) ($despesas['Obra'] ?? 0);
            $custoIncorridoTotal += (float) ($linha['custos_totais'] ?? 0);
        }

        $percentualExecucaoObra = $custoOrcadoObra > 0 ? min(1, $custoIncorridoObra / $custoOrcadoObra) : 0;
        $receitaTotalVendas = (float) ($dre['receita_total_vendas'] ?? 0);
        $receitaReconhecida = $receitaTotalVendas * $percentualExecucaoObra;
        $lucroBrutoContabil = $receitaReconhecida - $custoIncorridoTotal;
        $margemBrutaContabil = $receitaReconhecida > 0 ? ($lucroBrutoContabil / $receitaReconhecida) : 0;

        return [
            'custo_orcado_obra' => round($custoOrcadoObra, 2),
            'custo_incorrido_obra' => round($custoIncorridoObra, 2),
            'percentual_execucao_obra' => round($percentualExecucaoObra * 100, 2),
            'receita_reconhecida_poc' => round($receitaReconhecida, 2),
            'custo_incorrido_total' => round($custoIncorridoTotal, 2),
            'lucro_bruto_contabil' => round($lucroBrutoContabil, 2),
            'margem_bruta_contabil_percentual' => round($margemBrutaContabil * 100, 2),
        ];
    }

    private function calcularQuadroPocMensal(array $fluxo, array $dre, array $dadosProdutos): array
    {
        $quadro = [];
        $custoOrcadoObra = max(0.0, (float) (($dadosProdutos['custoObraHabitacao'] ?? 0) + ($dadosProdutos['custoInfraestrutura'] ?? 0)));
        $receitaTotalVendas = max(0.0, (float) ($dre['receita_total_vendas'] ?? 0));
        $custoObraAcumulado = 0.0;
        $receitaReconhecidaAcumulada = 0.0;

        foreach ($fluxo as $mes => $linha) {
            $despesas = $linha['despesas'] ?? [];
            $custoObraMes = max(0.0, (float) ($despesas['Obra'] ?? 0));
            $custoObraAcumulado += $custoObraMes;
            $execucaoAcumulada = $custoOrcadoObra > 0 ? min(1, $custoObraAcumulado / $custoOrcadoObra) : 0.0;
            $receitaReconhecidaAlvo = $receitaTotalVendas * $execucaoAcumulada;
            $receitaReconhecidaMes = max(0.0, $receitaReconhecidaAlvo - $receitaReconhecidaAcumulada);
            $receitaReconhecidaAcumulada += $receitaReconhecidaMes;
            $custoTotalMes = (float) ($linha['custos_totais'] ?? 0);
            $resultadoContabilMes = $receitaReconhecidaMes - $custoTotalMes;

            $quadro[$mes] = [
                'percentual_execucao_obra_acumulado' => round($execucaoAcumulada * 100, 2),
                'custo_obra_mes' => round($custoObraMes, 2),
                'custo_obra_acumulado' => round($custoObraAcumulado, 2),
                'receita_reconhecida_poc_mes' => round($receitaReconhecidaMes, 2),
                'receita_reconhecida_poc_acumulada' => round($receitaReconhecidaAcumulada, 2),
                'resultado_contabil_mes' => round($resultadoContabilMes, 2),
            ];
        }

        return [
            'meses' => $quadro,
            'resumo' => [
                'custo_orcado_obra' => round($custoOrcadoObra, 2),
                'custo_obra_acumulado' => round($custoObraAcumulado, 2),
                'receita_reconhecida_poc_total' => round($receitaReconhecidaAcumulada, 2),
            ],
        ];
    }

    private function calcularQuadroPocMensalPorBlocos(array $fluxo, array $dre, array $dadosProdutos): array
    {
        $quadro = [];
        $custoOrcadoObra = max(0.0, (float) (($dadosProdutos['custoObraHabitacao'] ?? 0) + ($dadosProdutos['custoInfraestrutura'] ?? 0)));
        $receitaTotalVendas = max(0.0, (float) ($dre['receita_total_vendas'] ?? 0));
        $custoObraAcumulado = 0.0;
        $receitaPocAcumulada = 0.0;
        $acumulados = [
            'receita_poc' => 0.0,
            'custo_direto' => 0.0,
            'impostos' => 0.0,
            'operacional' => 0.0,
            'financeiro' => 0.0,
            'resultado' => 0.0,
        ];

        foreach ($fluxo as $mes => $linha) {
            $despesas = $linha['despesas'] ?? [];
            $custoObraMes = max(0.0, (float) ($despesas['Obra'] ?? 0));
            $custoObraAcumulado += $custoObraMes;
            $execucaoAcumulada = $custoOrcadoObra > 0 ? min(1, $custoObraAcumulado / $custoOrcadoObra) : 0.0;
            $receitaPocAlvo = $receitaTotalVendas * $execucaoAcumulada;
            $receitaPocMes = max(0.0, $receitaPocAlvo - $receitaPocAcumulada);
            $receitaPocAcumulada += $receitaPocMes;

            $impostosMes = max(0.0, (float) ($despesas['Tributos'] ?? 0));
            $operacionalMes = max(0.0, (float) ($despesas['Operacional'] ?? 0));
            $financeiroMes = max(0.0, (float) ($despesas['Financeiro'] ?? 0));
            $custosTotaisMes = max(0.0, (float) ($linha['custos_totais'] ?? 0));
            $custoDiretoMes = max(0.0, $custosTotaisMes - $impostosMes - $operacionalMes - $financeiroMes);
            $resultadoContabilMes = $receitaPocMes - ($custoDiretoMes + $impostosMes + $operacionalMes + $financeiroMes);

            $acumulados['receita_poc'] += $receitaPocMes;
            $acumulados['custo_direto'] += $custoDiretoMes;
            $acumulados['impostos'] += $impostosMes;
            $acumulados['operacional'] += $operacionalMes;
            $acumulados['financeiro'] += $financeiroMes;
            $acumulados['resultado'] += $resultadoContabilMes;

            $quadro[$mes] = [
                'receita_reconhecida_poc_mes' => round($receitaPocMes, 2),
                'receita_reconhecida_poc_acumulada' => round($acumulados['receita_poc'], 2),
                'custo_direto_mes' => round($custoDiretoMes, 2),
                'custo_direto_acumulado' => round($acumulados['custo_direto'], 2),
                'impostos_mes' => round($impostosMes, 2),
                'impostos_acumulado' => round($acumulados['impostos'], 2),
                'despesas_operacionais_mes' => round($operacionalMes, 2),
                'despesas_operacionais_acumulado' => round($acumulados['operacional'], 2),
                'despesas_financeiras_mes' => round($financeiroMes, 2),
                'despesas_financeiras_acumulado' => round($acumulados['financeiro'], 2),
                'resultado_contabil_mes' => round($resultadoContabilMes, 2),
                'resultado_contabil_acumulado' => round($acumulados['resultado'], 2),
                'receita_caixa_mes' => round((float) ($linha['receita_total'] ?? 0), 2),
                'percentual_execucao_obra_acumulado' => round($execucaoAcumulada * 100, 2),
            ];
        }

        $margemContabil = $acumulados['receita_poc'] > 0 ? ($acumulados['resultado'] / $acumulados['receita_poc']) : 0;

        return [
            'meses' => $quadro,
            'resumo' => [
                'receita_reconhecida_poc_total' => round($acumulados['receita_poc'], 2),
                'custo_direto_total' => round($acumulados['custo_direto'], 2),
                'impostos_total' => round($acumulados['impostos'], 2),
                'despesas_operacionais_total' => round($acumulados['operacional'], 2),
                'despesas_financeiras_total' => round($acumulados['financeiro'], 2),
                'resultado_contabil_total' => round($acumulados['resultado'], 2),
                'margem_contabil_percentual' => round($margemContabil * 100, 2),
            ],
        ];
    }

    private function calcularIndicadoresVsoJanelas(array $fluxo, array $dadosProdutos): array
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
                $resultado[$janela . 'm'] = [
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

            $resultado[$janela . 'm'] = [
                'ultimo_percentual' => round(($ultimo / $unidadesConstrutora) * 100, 2),
                'maximo_percentual' => round(($maximo / $unidadesConstrutora) * 100, 2),
                'media_percentual' => round(($media / $unidadesConstrutora) * 100, 2),
            ];
        }

        return [
            'vso_janelas' => $resultado,
        ];
    }

    /**
     * Agrega curva_obra de todos os produtos (peso = quantidade_unidades).
     */
    private function agregarCurvaObra(array $produtos): array
    {
        $curvas = [];
        $pesos = [];

        foreach ($produtos as $produto) {
            $curva = $this->curvaService->extrairCurva($produto['curva_obra'] ?? null);
            if (empty($curva)) {
                continue;
            }
            $curvas[] = $this->curvaService->normalizarCurva($curva);
            $pesos[] = $produto['quantidade_unidades'] ?? 1;
        }

        if (empty($curvas)) {
            return [];
        }

        // Interpolar todas as curvas para o mesmo tamanho (maior)
        $maxMeses = max(array_map('count', $curvas));

        foreach ($curvas as $i => $curva) {
            if (count($curva) < $maxMeses) {
                $curvas[$i] = $this->curvaService->interpolarCurva($curva, $maxMeses);
            }
        }

        // Média ponderada
        $totalPeso = array_sum($pesos);
        $agregada = array_fill(0, $maxMeses, 0.0);

        foreach ($curvas as $i => $curva) {
            foreach ($curva as $j => $valor) {
                $agregada[$j] += $valor * ($pesos[$i] / $totalPeso);
            }
        }

        return $this->curvaService->normalizarCurva($agregada);
    }

    private function calcularTir(array $fluxo, float $estimativa = 0.01): float
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

        // Tenta múltiplas estimativas para aumentar chance de convergência
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
                    break; // taxa inválida
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
