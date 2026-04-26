<?php

namespace App\Services\Tenant\Viabilidade;

use App\Enums\PerfilFinanciamento;
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
    private const TAXA_CORRECAO_OBRA_ANUAL = 0.05;   // 5% a.a.

    private const TAXA_CORRECAO_POS_ANUAL = 0.045;  // 4.5% a.a.

    private const JUROS_POS_CHAVE_MENSAL = 0.01;   // 1% a.m.

    private const PRAZO_POS_CHAVE_PADRAO = 36;     // parcelas pós-chave

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
            $terreno = $this->buscarTerreno($terrenoId);
            $viabilidade = $this->buscarViabilidade($terrenoId, $viabilidadeRef);
            $params = $this->montarParametros($viabilidade);
            $dadosProdutos = $this->processarProdutos($terreno, $params, $customProdutos);

            // Mesclar parâmetros vindos dos produtos (fonte de verdade)
            $params = $this->mesclarParametrosProduto($params, $dadosProdutos);

            // Validação: curvas obrigatórias devem estar preenchidas nos produtos
            $validacaoCurvas = $this->curvaService->validarCurvasObrigatorias($dadosProdutos['produtos']);
            if (! $validacaoCurvas['valid']) {
                throw new Exception(
                    'Curvas obrigatórias não preenchidas nos produtos: '.implode(', ', $validacaoCurvas['faltando'])
                );
            }

            if ($dadosProdutos['totalUnidades'] === 0 || $dadosProdutos['vgv'] === 0) {
                throw new Exception('Não foi possível calcular dados válidos dos produtos.');
            }

            // Agregar curva_obra baseada no prazo de obra (CurvaService, Curva S padrão)
            $dadosProdutos['curvaObraAgregada'] = $this->agregarCurvaObra($params['mesesObra']);

            // 2. Calcular datas dos períodos
            $datas = $this->calcularPeriodos($dadosProdutos['dataInicio'], $params);

            // 3. Montar contexto limpo e pré-popular caches
            $ctx = new ViabilidadeFluxoContext;
            $ctx->perfil = $params['perfilFinanciamento'];

            $this->preCalcularRecebiveis($dadosProdutos['produtos'], $datas, $params, $ctx);

            if ($ctx->perfil->isCef()) {
                $this->inicializarCachesCef($dadosProdutos, $datas, $ctx);
            }

            $ctx->parceriaVgvTotal = max(0.0, ($params['parceriaVgv'] ?? 0.0) * ($dadosProdutos['vgv'] ?? 0.0));
            $ctx->parceriaVgvPago = 0.0;

            // 4. Iterar mês a mês
            $fluxo = [];
            $saldoAcumulado = 0.0;
            $fluxoTir = [];  // com CEF — base para tir_operacional
            $fluxoTirSemCef = [];  // sem CEF — base para tir_sem_cef
            $totais = [
                'receita' => 0.0,
                'custo_direto' => 0.0,
                'impostos' => 0.0,
                'custos_operacionais' => 0.0,
                'custos_financeiros' => 0.0,
                'lucro' => 0.0,
            ];

            $periodo = CarbonPeriod::create($datas['inicioIncorporacao'], '1 month', $datas['fimPos']);

            foreach ($periodo as $data) {
                $mes = $data->format('Y-m');

                $receitas = $this->calcularReceitas($mes, $dadosProdutos, $datas, $params, $ctx);
                $despesas = $this->calcularDespesas($mes, $receitas, $dadosProdutos, $datas, $params, $ctx);

                $lucroMes = $receitas['total'] - $despesas['total'];
                $saldoAcumulado += $lucroMes;

                // TIR sem CEF: apenas Recursos Próprios como receita
                $receitaRpMes = $receitas['detalhes']['Recursos Próprios'] ?? 0.0;
                $lucroSemCefMes = $receitaRpMes - $despesas['total'];

                $unidadesVendidasMes = ceil($ctx->vendasPorMes[$mes] ?? 0);

                $fluxo[$mes] = [
                    'periodo' => $this->identificarPeriodo($data, $datas),
                    'receita_total' => round($receitas['total'], 2),
                    'receitas' => $receitas['detalhes'],
                    'despesas' => array_filter($despesas['detalhes'], fn ($v) => abs($v) > 0.01),
                    'custos_totais' => round($despesas['total'], 2),
                    'lucro' => round($lucroMes, 2),
                    'saldo_acumulado' => round($saldoAcumulado, 2),
                    'unidades_vendidas' => round($unidadesVendidasMes, 2),
                ];

                $fluxoTir[] = ['data' => $data->copy(), 'valor' => $lucroMes];
                $fluxoTirSemCef[] = ['data' => $data->copy(), 'valor' => $lucroSemCefMes];

                $totais['receita'] += $receitas['total'];
                $totais['custo_direto'] += $despesas['categorias']['custo_direto'];
                $totais['impostos'] += $despesas['categorias']['impostos'];
                $totais['custos_operacionais'] += $despesas['categorias']['custos_operacionais'];
                $totais['custos_financeiros'] += $despesas['categorias']['custos_financeiros'];
                $totais['lucro'] += $lucroMes;
            }

            [$fluxoFinanceiro, $indicadoresFinanceiros] = $this->calcularIndicadoresFinanceiros($fluxo, $datas, $params, $dadosProdutos);
            $indicadoresVso = $this->calcularIndicadoresVso($fluxo, $dadosProdutos);
            $indicadoresVsoJanelas = $this->calcularIndicadoresVsoJanelas($fluxo, $dadosProdutos);

            $indicadores = [
                'tir_operacional' => $this->calcularTir($fluxoTir),
                'tir_sem_cef' => $this->calcularTir($fluxoTirSemCef),
                'exposicao_maxima_operacional' => collect($fluxo)->min('saldo_acumulado'),
                'margem_liquida' => $totais['receita'] > 0
                    ? ($totais['lucro'] / $totais['receita'])
                    : 0.0,
            ];

            // 5. Calcular DRE consolidada
            $dre = $this->calcularDre($fluxo, $dadosProdutos, $params);
            $dreContabilPoc = $this->calcularDreContabilPoc($fluxo, $dre, $dadosProdutos);
            $dreContabilPocMensal = $this->calcularQuadroPocMensal($fluxo, $dre, $dadosProdutos);
            $dreContabilPocMensalBlocos = $this->calcularQuadroPocMensalPorBlocos($fluxo, $dre, $dadosProdutos);
            $dreCaixa = $this->calcularDreCaixa($totais);
            $ponteReconcilicao = $this->calcularPonteReconcilicao($dreCaixa, $dre, $dreContabilPocMensalBlocos);

            return [
                'terreno' => $terreno,
                'vgv' => $dadosProdutos['vgvSemValorTerrenista'],
                'totalUnidades' => $dadosProdutos['totalUnidades'],
                'unidadesPermuta' => $dadosProdutos['permutas'],
                'areaConstruida' => $dadosProdutos['areaConstruida'],
                'custoTotal' => $dre['custo_total_projeto'],
                'produtos' => $dadosProdutos['produtos'],
                'dre_itens' => $dre,
                'dre_caixa' => $dreCaixa,
                'dre_contabil_poc' => $dreContabilPoc,
                'dre_contabil_poc_mensal' => $dreContabilPocMensal,
                'dre_contabil_poc_mensal_blocos' => $dreContabilPocMensalBlocos,
                'ponte_reconciliacao' => $ponteReconcilicao,
                'indicadores' => array_merge($dre['indicadores'], $indicadores, $indicadoresFinanceiros, $indicadoresVso, $indicadoresVsoJanelas),
                'dados_produtos' => [
                    'total_unidades' => $dadosProdutos['totalUnidades'],
                    'unidades_permuta' => $dadosProdutos['permutas'],
                    'area_construida_total' => $dadosProdutos['areaConstruida'],
                ],
                'fluxo_mensal' => $fluxo,
                'fluxo_mensal_financeiro' => $fluxoFinanceiro,
                'totais' => $totais,
                'parametros_utilizados' => $params,
            ];
        } catch (Exception $e) {
            Log::error('Erro ao gerar fluxo mensal: '.$e->getMessage(), [
                'terrenoId' => $terrenoId,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new Exception('Erro ao gerar fluxo mensal: '.$e->getMessage());
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
        $ctx ??= new ViabilidadeFluxoContext;

        // Atualizar acumulado de vendas com o mês atual
        $ctx->vendasAcumuladas += $ctx->vendasPorMes[$mes] ?? 0.0;

        // 1. Recursos Próprios (do cache pré-calculado) + parcelas atrasadas recuperadas
        $rp = $ctx->recursosProprios[$mes] ?? [];
        $totalRp = ($rp['sinal'] ?? 0.0) + ($rp['parcelas_obra'] ?? 0.0) + ($rp['parcelas_pos'] ?? 0.0);

        $totalAtrasadas = $ctx->parcelasAtrasadas[$mes] ?? 0.0;

        // 2. Recurso Terrenos (CEF) — apenas perfil CEF
        $rt = $ctx->perfil->isCef()
            ? $this->calcularRecursoTerrenos($mes, $dadosProdutos, $datas, $ctx)
            : ['valor' => 0.0];

        // 3. Medição de Obra (CEF) — apenas perfil CEF
        $mo = $ctx->perfil->isCef()
            ? $this->calcularMedicaoObra($mes, $dadosProdutos, $datas, $params, $ctx)
            : ['valor' => 0.0];

        $total = $totalRp + $totalAtrasadas + $rt['valor'] + $mo['valor'];

        return [
            'total' => $total,
            'juros_correcao' => ($rp['juros'] ?? 0.0) + ($rp['correcao'] ?? 0.0),
            'detalhes' => [
                'Recursos Próprios' => round($totalRp, 2),
                'Recursos Próprios (Atrasados)' => round($totalAtrasadas, 2),
                'Recurso Terrenos' => round($rt['valor'], 2),
                'Medição Obra' => round($mo['valor'], 2),
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
        $ctx ??= new ViabilidadeFluxoContext;
        $dataAtual = $this->parseMes($mes);
        $periodo = $this->identificarPeriodo($dataAtual, $datas);
        $vgv = $dadosProdutos['vgv'];
        $custoObra = $this->custoObraTotal($dadosProdutos);

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
        $percProdutosCef = $ctx->perfil->isCef() ? $params['percentualProdutosCef'] : 0.0;
        $financeiros = $receitas['total'] * ($percProdutosCef + $params['percentualOutrasDespesasFinanceiras']);

        // 5. Custo Terreno (proporcional à receita)
        $custoTerreno = $this->calcularCustoTerreno($mes, $receitas['total'], $dadosProdutos, $params);
        $pagamentoParceriaTerreno = $this->calcularPagamentoParceriaTerreno($mes, $receitas['total'], $datas, $params, $ctx);

        $detalhesOperacionais = [];
        foreach ($operacionais['detalhes'] as $nome => $valor) {
            $detalhesOperacionais['Operacional - '.$nome] = round($valor, 2);
        }

        $total = $diretos['total'] + $tributos + $operacionais['total'] + $financeiros + $custoTerreno + $pagamentoParceriaTerreno;

        return [
            'total' => $total,
            'detalhes' => array_merge($diretos['detalhes'], [
                'Tributos' => round($tributos, 2),
                'Operacional' => round($operacionais['total'], 2),
                'Financeiro' => round($financeiros, 2),
                'Custo Terreno' => round($custoTerreno, 2),
                'Pagamento Terreno (Parceria)' => round($pagamentoParceriaTerreno, 2),
            ], $detalhesOperacionais),
            'categorias' => [
                'custo_direto' => $diretos['total'] + $custoTerreno + $pagamentoParceriaTerreno,
                'impostos' => $tributos,
                'custos_operacionais' => $operacionais['total'],
                'custos_financeiros' => $financeiros,
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
        $vgv = $dadosProdutos['vgv'];
        $vgvSemTerrenista = $dadosProdutos['vgvSemValorTerrenista'];
        $vgvSemPermutas = $dadosProdutos['vgvSemUnidPermutas'];
        $totalUnidades = $dadosProdutos['totalUnidades'];

        // ── Receitas ──────────────────────────────────────────────────────────
        $receitaTotalVendas = $vgvSemTerrenista;
        $jurosCorrecoes = $dadosProdutos['correcaoSobreVgv'];
        $receitaBruta = $receitaTotalVendas + $jurosCorrecoes;

        $impostos = $this->impostosService->calcularImpostosDre($dadosProdutos['produtos'], $vgvSemTerrenista);
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
        $despesasOnerosas = $jurosPJ['juros_totais'];
        $ebit = $ebitda - $outrasDespFinanceiras - $despesasOnerosas;

        // ── Resultado ─────────────────────────────────────────────────────────
        $irpjCsll = $impostos['total_ir_csll'];
        $lucroLiquido = $ebit - $irpjCsll;

        $custoTotalProjeto = $custosDiretosTotal + $despesasOperacionaisTotal
            + $outrasDespFinanceiras + $despesasOnerosas + $irpjCsll + $impostos['total'];

        return [
            'receita_total_vendas' => round($receitaTotalVendas, 2),
            'juros_correcoes' => round($jurosCorrecoes, 2),
            'receita_bruta' => round($receitaBruta, 2),
            'pis_cofins_outros' => round($impostos['pis'] + $impostos['cofins'], 2),
            'iss' => round($impostos['iss'], 2),
            'outras_deducoes' => round($impostos['outras_deducoes'], 2),
            'receita_liquida' => round($receitaLiquida, 2),
            'custo_terreno' => round($custoTerreno, 2),
            'comissao' => round($comissao, 2),
            'incorporacao' => round($incorporacao, 2),
            'incorporacao_detalhes' => [
                'ri' => round($incorporacao * $params['incorporacaoRi'], 2),
                'entrega' => round($incorporacao * $params['incorporacaoEntrega'], 2),
                'projetos' => round($incorporacao * (1 - $params['incorporacaoRi'] - $params['incorporacaoEntrega']), 2),
            ],
            'infra_casas' => round($infraCasas, 2),
            'infra_lotes' => round($infraLotes, 2),
            'area_comum' => round($areaComum, 2),
            'contrapartidas' => round($contrapartidas, 2),
            'canteiro_total' => round($canteiroTotal, 2),
            'mo_administrativa_total' => round($moAdministrativaTotal, 2),
            'seguros' => round($seguros, 2),
            'assistencia_tecnica' => round($assistenciaTecnica, 2),
            'custo_total_obra' => round($custoTotalObra, 2),
            'custos_diretos_total' => round($custosDiretosTotal, 2),
            'lucro_bruto' => round($lucroBruto, 2),
            'despesas_comerciais' => round($despesasComerciais['total'], 2),
            'despesas_comerciais_detalhes' => $despesasComerciais['detalhes'],
            'marketing' => round($marketingTotal, 2),
            'itbi_iptu' => round($itbiIptu, 2),
            'registro' => round($registroTotal, 2),
            'tx_medicao_contratacao' => round($txMedicao, 2),
            'contratos_caixa' => round($contratosCef, 2),
            'produtos_caixa' => round($produtosCef, 2),
            'despesas_operacionais_total' => round($despesasOperacionaisTotal, 2),
            'ebitda' => round($ebitda, 2),
            'outras_despesas_financeiras' => round($outrasDespFinanceiras, 2),
            'despesas_onerosas_bancos' => round($despesasOnerosas, 2),
            'juros_pj' => round($jurosPJ['juros_totais'], 2),
            'juros_pj_detalhes' => [
                'valor_antecipado' => round($jurosPJ['valor_antecipado'], 2),
                'taxa_mensal' => $jurosPJ['taxa_mensal'],
                'carencia_meses' => $jurosPJ['carencia_meses'] ?? 0,
                'amortizacao_parcelas' => $jurosPJ['amortizacao_parcelas'] ?? 0,
            ],
            'ebit' => round($ebit, 2),
            'irpj_csll' => round($irpjCsll, 2),
            'lucro_liquido_projeto' => round($lucroLiquido, 2),
            'custo_total_projeto' => round($custoTotalProjeto, 2),
            'indicadores' => [
                'vgv_total' => round($receitaTotalVendas, 2),
                'lucro_liquido' => round($lucroLiquido, 2),
                'margem_liquida_percentual' => $receitaTotalVendas > 0 ? round(($lucroLiquido / $receitaTotalVendas) * 100, 2) : 0,
                'margem_liquida_sobre_rol' => $receitaLiquida > 0 ? round(($lucroLiquido / $receitaLiquida) * 100, 2) : 0,
                'margem_liquida_sobre_vgv_sem_permuta' => $vgvSemPermutas > 0 ? round(($lucroLiquido / $vgvSemPermutas) * 100, 2) : 0,
                'margem_bruta_percentual' => $receitaLiquida > 0 ? round(($lucroBruto / $receitaLiquida) * 100, 2) : 0,
                'margem_ebitda_percentual' => $receitaLiquida > 0 ? round(($ebitda / $receitaLiquida) * 100, 2) : 0,
                'margem_ebit_percentual' => $receitaLiquida > 0 ? round(($ebit / $receitaLiquida) * 100, 2) : 0,
                'roi_percentual' => $custosDiretosTotal > 0 ? round(($lucroLiquido / $custosDiretosTotal) * 100, 2) : 0,
                'total_custos_diretos' => round($custosDiretosTotal, 2),
                'custo_total_projeto' => round($custoTotalProjeto, 2),
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
        $parceriaBase = $dadosProdutos['vgv'] ?? $vgv;
        $custoTerreno = $params['compraTerreno']
            + ($params['parceriaVgv'] * $parceriaBase)
            + ($dadosProdutos['permutas'] * ($dadosProdutos['custoCasaM2'] ?? 0))
            + ($dadosProdutos['permutas'] * ($dadosProdutos['custoInfraM2'] ?? 0));

        // Comissão: sobre custo terreno (planilha DRE J62 = J60 × D62)
        $comissao = $params['percentualComissao'] * abs($custoTerreno);

        $incorporacao = $params['percentualIncorporacao'] * $vgv;
        $infraCasas = $dadosProdutos['custoObraHabitacao'];
        $infraLotes = $dadosProdutos['custoInfraestrutura'] + $dadosProdutos['custoNaoIncidente'];
        $areaComum = $params['custoAreaComum'] * $totalUnidades;
        $contrapartidas = $params['percentualContrapartidas'] * $vgv;
        $canteiroTotal = $params['canteiroMensal'] * $params['mesesObra'];
        $moAdministrativaTotal = $params['moAdministrativa'] * $params['mesesObra'];
        $seguros = $this->calcularSegurosPorTipologia($dadosProdutos, $params);

        $custoTotalObra = $infraCasas + $infraLotes + $areaComum + $contrapartidas + $canteiroTotal;

        // Assistência Técnica: % sobre (casas+lotes+contrapartidas+área comum)
        $baseAssistencia = $infraCasas + $infraLotes + $contrapartidas + $areaComum;
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
        $despesasComerciais = $this->calcularDespesasComerciaisDetalhadas($dadosProdutos, $params);
        $marketingTotal = $this->calcularMarketingDetalhado($dadosProdutos, $params);
        $itbiIptu = $this->calcularItbiPorTipologia($dadosProdutos, $params);
        $registroTotal = $this->calcularRegistroPorTipologia($dadosProdutos, $params);
        $txMedicao = $this->calcularTxMedicao($dadosProdutos, $params);
        $contratosCef = $this->calcularContratosCef($dadosProdutos, $params);
        $produtosCef = $this->calcularProdutosCefPorTipologia($dadosProdutos, $params);
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
        $vgvSemTerrenista = $dadosProdutos['vgvSemValorTerrenista'] ?? 0.0;
        $unidadesConstrutora = $this->unidadesConstrutora($dadosProdutos);
        $ticketMedio = $unidadesConstrutora > 0 ? $vgvSemTerrenista / $unidadesConstrutora : 0.0;

        [$comissaoBase, $comissaoHouse, $comissaoImobiliarias] = $this->calcularComissaoBase(
            $unidadesConstrutora * $ticketMedio,
            $params
        );

        $gastosStand = ($params['gastosMensaisStand'] ?? 0) * $vgvSemTerrenista * ($params['mesesLancamento'] ?? 0);
        $bonusCca = ($params['bonusCca'] ?? 0) * $unidadesConstrutora;
        $bonusGerente = $comissaoBase * ($params['bonusGerente'] ?? 0);
        $bonusGerenteRegional = $comissaoBase * ($params['bonusGerenteRegional'] ?? 0);
        $bonusCredito = $comissaoBase * ($params['bonusCredito'] ?? 0);
        $bonusGestorComercial = $comissaoBase * ($params['bonusGestorComercial'] ?? 0);
        $ajudaGerente = ($params['ajudaCustoGerente'] ?? 0) * ($params['mesesLancamento'] ?? 0);
        $ajudaGerenteRegional = ($params['ajudaCustoGerenteRegional'] ?? 0) * ($params['mesesLancamento'] ?? 0);
        $reembolsoLogistica = ($params['reembolsoLogistica'] ?? 0) * ($params['mesesLancamento'] ?? 0);

        $total = ($params['standVendas'] ?? 0)
            + ($params['mobiliaDecoracao'] ?? 0)
            + $gastosStand
            + ($comissaoBase * ($params['pagamentoComissaoVenda'] ?? 0))
            + ($comissaoBase * ($params['pagamentoComissaoDesligamento'] ?? 0))
            + $bonusCca + $bonusGerente + $bonusGerenteRegional
            + $bonusCredito + $bonusGestorComercial
            + $ajudaGerente + $ajudaGerenteRegional + $reembolsoLogistica;

        return [
            'total' => $total,
            'detalhes' => [
                'stand_vendas' => round($params['standVendas'] ?? 0, 2),
                'mobilia_decoracao' => round($params['mobiliaDecoracao'] ?? 0, 2),
                'gastos_mensais_stand' => round($gastosStand, 2),
                'comissao_house' => round($comissaoHouse, 2),
                'comissao_imobiliarias' => round($comissaoImobiliarias, 2),
                'bonus_cca' => round($bonusCca, 2),
                'bonus_gerente' => round($bonusGerente, 2),
                'bonus_gerente_regional' => round($bonusGerenteRegional, 2),
                'bonus_credito' => round($bonusCredito, 2),
                'bonus_gestor_comercial' => round($bonusGestorComercial, 2),
                'ajuda_custo_gerente' => round($ajudaGerente, 2),
                'ajuda_custo_gerente_regional' => round($ajudaGerenteRegional, 2),
                'reembolso_logistica' => round($reembolsoLogistica, 2),
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
        if (($params['perfilFinanciamento'] ?? null)?->isProprio()) {
            return 0.0;
        }

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
        if (($params['perfilFinanciamento'] ?? null)?->isProprio()) {
            return 0.0;
        }

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
        if (($params['perfilFinanciamento'] ?? null)?->isProprio()) {
            return 0.0;
        }

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
                'terrenoProdutos' => fn ($q) => $q->select(['terreno_id', 'produto_id', 'unidades', 'valor', 'permuta', 'id', 'pgto_por_lote']),
                'terrenoProdutos.produto' => fn ($q) => $q->select([
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
                    'avaliacao_lotesCef',
                    'baloes_anuais',
                    'balao_entrega_modo',
                    'gastos_mensaisStand',
                    'comissao_house',
                    'porcentagem_comissaoHouse',
                    'porcentagem_comissaoImobs',
                    'pagto_comissaoNaVenda',
                    'marketing_lancamento',
                    'marketing_antesLancamento',
                    'custo_contratacaoCef',
                    'pj_taxaJuros',
                    'pj_carenciaPosObra',
                    'pj_qtdeParcelasPosCarencia',
                    'assist_tecnica1',
                    'assist_tecnica2',
                    'assist_tecnica3',
                    'assist_tecnica4',
                    'assist_tecnica5',
                    'incorp_ri',
                    'incorp_entrega',
                    'incorp_ateLancamento',
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

        $perfilValue = $v?->perfil_financiamento;
        $perfilStr = $perfilValue instanceof PerfilFinanciamento
            ? $perfilValue->value
            : $d['perfil_financiamento'];

        return [
            'percentualImpostos' => (($v->pis_cofins ?? $d['pis_cofins']) + ($v->iss ?? $d['iss']) + ($v->outros_impostos ?? $d['outros_impostos'])) / 100,
            'percentualPisCofins' => ($v->pis_cofins ?? $d['pis_cofins']) / 100,
            'percentualIss' => ($v->iss ?? $d['iss']) / 100,
            'percentualOutrosImpostos' => ($v->outros_impostos ?? $d['outros_impostos']) / 100,
            'percentualComissao' => ($v->comissao ?? $d['comissao']) / 100,
            'parceriaVgv' => ($v->parceria_vgv ?? $d['parceria_vgv']) / 100,
            'infraNaoIncidente' => ($v->infra_nao_incidente ?? $d['infra_nao_incidente']) / 100,
            'percentualIncorporacao' => ($v->incorporacao ?? $d['incorporacao']) / 100,
            'custoAreaComum' => $v->area_comum ?? $d['area_comum'],
            'percentualContrapartidas' => ($v->contrapartidas ?? $d['contrapartidas']) / 100,
            'canteiroMensal' => $v->canteiro_mensal ?? $d['canteiro_mensal'],
            'moAdministrativa' => $v->mo_administrativa ?? $d['mo_administrativa'],
            'percentualSeguros' => ($v->seguros ?? $d['seguros']) / 100,
            'percentualAssistenciaTecnica' => ($v->assistencia_tecnica ?? $d['assistencia_tecnica']) / 100,
            'percentualDespesasComerciais' => ($v->despesas_comerciais ?? $d['despesas_comerciais']) / 100,
            'standVendas' => $v->stand_vendas ?? $d['stand_vendas'],
            'mobiliaDecoracao' => $v->mobilia_decoracao ?? $d['mobilia_decoracao'],
            'ajudaCustoGerente' => $v->ajuda_custo_gerente ?? $d['ajuda_custo_gerente'],
            'ajudaCustoGerenteRegional' => $v->ajuda_custo_gerente_regional ?? $d['ajuda_custo_gerente_regional'],
            'reembolsoLogistica' => $v->reembolso_logistica ?? $d['reembolso_logistica'],
            'bonusCca' => $v->bonus_cca ?? $d['bonus_cca'],
            'bonusGerente' => ($v->bonus_gerente ?? $d['bonus_gerente']) / 100,
            'bonusGerenteRegional' => ($v->bonus_gerente_regional ?? $d['bonus_gerente_regional']) / 100,
            'bonusCredito' => ($v->bonus_credito ?? $d['bonus_credito']) / 100,
            'bonusGestorComercial' => ($v->bonus_gestor_comercial ?? $d['bonus_gestor_comercial']) / 100,
            'pagamentoComissaoDesligamento' => ($v->pagamento_comissao_desligamento ?? $d['pagamento_comissao_desligamento']) / 100,
            'parcelamentoComissaoMeses' => (int) ($v->parcelamento_comissao_meses ?? $d['parcelamento_comissao_meses']),
            'percentualMarketing' => ($v->marketing ?? $d['marketing']) / 100,
            'custoItbiIptu' => ($v->itbi_iptu ?? $d['itbi_iptu']) / 100,
            'custoRegistro' => $v->registro ?? $d['registro'],
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
            'percentualAntecipacaoPj' => ($v->percentual_antecipacao_pj ?? $d['percentual_antecipacao_pj']) / 100,
            'aporteAdicionalMensal' => $v->aporte_adicional_mensal ?? $d['aporte_adicional_mensal'],
            'devolucaoAportePercentual' => ($v->devolucao_aporte_percentual ?? $d['devolucao_aporte_percentual']) / 100,
            'distribuicaoLucrosPercentualObra' => ($v->distribuicao_lucros_percentual_obra ?? $d['distribuicao_lucros_percentual_obra']) / 100,
            'taxaExposicaoAplicada' => ($v->taxa_exposicao_aplicada ?? $d['taxa_exposicao_aplicada']) / 100,
            'perfilFinanciamento' => PerfilFinanciamento::tryFrom((string) $perfilStr) ?? PerfilFinanciamento::CEF,
            'inadimplencia' => (float) ($d['inadimplencia'] ?? 0.10),
            'atrasoMeses' => (int) ($d['atraso_meses'] ?? 2),
            'taxaPerda' => (float) ($d['taxa_perda'] ?? 0.02),
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
                'baloes_anuais' => $produto->baloes_anuais ?? [],
                'balao_entrega_modo' => $produto->balao_entrega_modo ?? 'saldo_restante',
                'imposto_tributos' => ($produto->imposto_tributos ?? 0) / 100,
                'imposto_iss' => ($produto->imposto_iss ?? 0) / 100,
                'imposto_outros' => ($produto->imposto_outros ?? 0) / 100,
                'gastos_mensais_stand' => (float) ($produto->gastos_mensaisStand ?? 0),
                'comissao_house' => (float) ($produto->comissao_house ?? 0) / 100,
                'porcentagem_comissao_house' => (float) ($produto->porcentagem_comissaoHouse ?? 0) / 100,
                'porcentagem_comissao_imobs' => (float) ($produto->porcentagem_comissaoImobs ?? 0) / 100,
                'pagto_comissao_venda' => (float) ($produto->pagto_comissaoNaVenda ?? 0) / 100,
                'marketing_lancamento' => (float) ($produto->marketing_lancamento ?? 0) / 100,
                'marketing_antes_lancamento' => (int) ($produto->marketing_antesLancamento ?? 0),
                'custo_contratacao_cef' => (float) ($produto->custo_contratacaoCef ?? 0),
                'pj_taxa_juros' => (float) ($produto->pj_taxaJuros ?? 0) / 100,
                'pj_carencia_pos_obra' => (int) ($produto->pj_carenciaPosObra ?? 0),
                'pj_qtde_parcelas' => (int) ($produto->pj_qtdeParcelasPosCarencia ?? 0),
                'assist_tecnica_curva' => $this->extrairAssistenciaTecnicaProduto($produto),
                'incorp_ri' => (float) ($produto->incorp_ri ?? 0) / 100,
                'incorp_entrega' => (float) ($produto->incorp_entrega ?? 0) / 100,
                'incorp_ate_lancamento' => (float) ($produto->incorp_ateLancamento ?? 0) / 100,
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

    /**
     * Extrai a curva de assistência técnica do produto (5 colunas → array).
     *
     * @return array<int, float>
     */
    private function extrairAssistenciaTecnicaProduto($produto): array
    {
        return [
            (float) ($produto->assist_tecnica1 ?? 50),
            (float) ($produto->assist_tecnica2 ?? 20),
            (float) ($produto->assist_tecnica3 ?? 10),
            (float) ($produto->assist_tecnica4 ?? 10),
            (float) ($produto->assist_tecnica5 ?? 10),
        ];
    }

    /**
     * Mescla parâmetros vindos dos produtos nos $params do projeto.
     *
     * Para campos que todos os produtos devem compartilhar (ex: taxa de juros PJ),
     * usa média ponderada por quantidade de unidades. Se nenhum produto tiver
     * o campo preenchido, mantém o valor original dos $params.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $dadosProdutos
     * @return array<string, mixed>
     */
    private function mesclarParametrosProduto(array $params, array $dadosProdutos): array
    {
        $produtos = $dadosProdutos['produtos'] ?? [];
        if (empty($produtos)) {
            return $params;
        }

        $extrair = function (string $campo, mixed $fallback) use ($produtos): mixed {
            // Para arrays (ex: assistencia_tecnica_curva), retorna o primeiro encontrado
            $primeiro = $produtos[0][$campo] ?? null;
            if (is_array($primeiro) && !empty($primeiro)) {
                return $primeiro;
            }

            $somaPonderada = 0.0;
            $unidadesComValor = 0;
            foreach ($produtos as $p) {
                $valor = $p[$campo] ?? null;
                if ($valor !== null && $valor != 0 && !is_array($valor)) {
                    $unidades = (int) ($p['quantidade_unidades'] ?? 0);
                    $somaPonderada += (float) $valor * $unidades;
                    $unidadesComValor += $unidades;
                }
            }
            if ($unidadesComValor > 0) {
                return $somaPonderada / $unidadesComValor;
            }
            foreach ($produtos as $p) {
                $valor = $p[$campo] ?? null;
                if ($valor !== null && $valor != 0 && !is_array($valor)) {
                    return $valor;
                }
            }
            return $fallback;
        };

        // Parâmetros que vêm do Produto: usar o valor do produto, com fallback no config
        $params['gastosMensaisStand'] = (float) $extrair('gastos_mensais_stand', $params['gastosMensaisStand'] ?? 0.01);
        $params['comissaoHousePercentual'] = (float) $extrair('comissao_house', $params['comissaoHousePercentual'] ?? 0.03);
        $params['percentualVendasHouse'] = (float) $extrair('porcentagem_comissao_house', $params['percentualVendasHouse'] ?? 0.50);
        $params['comissaoImobiliariasPercentual'] = (float) $extrair('porcentagem_comissao_imobs', $params['comissaoImobiliariasPercentual'] ?? 0.035);
        $params['pagamentoComissaoVenda'] = (float) $extrair('pagto_comissao_venda', $params['pagamentoComissaoVenda'] ?? 0.50);
        $params['marketingLancamento'] = (float) $extrair('marketing_lancamento', $params['marketingLancamento'] ?? 0.25);
        $params['marketingInicioAntesLancamento'] = (int) $extrair('marketing_antes_lancamento', $params['marketingInicioAntesLancamento'] ?? 3);
        $params['custoMedicaoContratacao'] = (float) $extrair('custo_contratacao_cef', $params['custoMedicaoContratacao'] ?? 2000);
        $params['taxaJurosPj'] = (float) $extrair('pj_taxa_juros', $params['taxaJurosPj'] ?? 0.105);
        $params['carenciaPjMeses'] = (int) $extrair('pj_carencia_pos_obra', $params['carenciaPjMeses'] ?? 6);
        $params['amortizacaoPjParcelas'] = (int) $extrair('pj_qtde_parcelas', $params['amortizacaoPjParcelas'] ?? 18);
        $params['assistenciaTecnicaCurva'] = $extrair('assist_tecnica_curva', $params['assistenciaTecnicaCurva'] ?? [50, 20, 10, 10, 10]);
        $params['incorporacaoRi'] = (float) $extrair('incorp_ri', $params['incorporacaoRi'] ?? 0.30);
        $params['incorporacaoEntrega'] = (float) $extrair('incorp_entrega', $params['incorporacaoEntrega'] ?? 0.15);
        $params['incorporacaoAteLancamento'] = (float) $extrair('incorp_ate_lancamento', $params['incorporacaoAteLancamento'] ?? 0.80);

        return $params;
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
     * Pré-calcula todos os recebíveis (recursos próprios) dos clientes.
     *
     * Suporta dois modelos de recebíveis, selecionados pelo perfil:
     *
     * Perfil CEF (comportamento original, inalterado):
     *   - Sinal: parcelado no lançamento ou à vista
     *   - Obra: parcelas mensais com correção composta
     *   - Pós-chave: SAC com juros + correção sobre saldo devedor
     *
     * Perfil Próprio (modelo de balões):
     *   - Entrada (% sinal): mesmo comportamento
     *   - Parcelas mensais: distribui linearmente ao longo da obra
     *   - Balões anuais: % do preço cobrado a cada 12 meses da venda
     *   - Balão na entrega: saldo restante (quitação) no mês da entrega
     */
    private function preCalcularRecebiveis(array $produtos, array $datas, array $params, ViabilidadeFluxoContext $ctx): void
    {
        if ($ctx->perfil->isCef()) {
            $this->preCalcularRecebiveisCef($produtos, $datas, $params, $ctx);
        } else {
            $this->preCalcularRecebiveisProprio($produtos, $datas, $params, $ctx);
        }

        $this->aplicarInadimplencia($ctx, $params);
    }

    /**
     * Modelo CEF: sinal + obra (correção) + pós-chave (SAC).
     * Comportamento IDÊNTICO ao preCalcularRecursosProprios original.
     */
    private function preCalcularRecebiveisCef(array $produtos, array $datas, array $params, ViabilidadeFluxoContext $ctx): void
    {
        $ctx->recursosProprios = [];

        $dataLancamento = $datas['dataLancamento'];
        $dataEntrega = $datas['dataEntrega'];

        $prazoLancamento = $params['mesesLancamento'];
        $prazoObra = $params['mesesObra'];
        $prazoPosChave = 36;

        foreach ($produtos as $produto) {
            $curvaVendas = $this->curvaService->extrairCurva($produto['curva_vendas'] ?? null);
            $curvaVendas = $this->curvaService->normalizarCurva($curvaVendas);

            $unidadesProduto = $produto['quantidade_unidades'] ?? 1;
            $precoProduto = $produto['preco'] ?? 0;
            $fin = $produto['financeiro'];

            $percentualSinal = ($fin['sinal'] ?? 2) / 100;
            $percentualObra = ($fin['parcela_obra'] ?? 9) / 100;
            $percentualPos = ($fin['parcela_posChave'] ?? 9) / 100;
            $qtdParcelasPos = max(1, (int) ($fin['qtde_parcelas_posChave'] ?? $prazoPosChave));

            // Taxas de correção e juros do Produto (fonte de verdade)
            $taxaCorrecaoObraAnual = ((float) ($fin['correcao_anualObra'] ?? 5)) / 100;
            $taxaCorrecaoPosAnual = ((float) ($fin['correcao_anualPosChave'] ?? 4.5)) / 100;
            $jurosMensalPos = ((float) ($fin['juros_mensalPosChave'] ?? 1)) / 100;

            $r_obra = pow(1 + $taxaCorrecaoObraAnual, 1 / 12.0) - 1;
            $r_pos = pow(1 + $taxaCorrecaoPosAnual, 1 / 12.0) - 1;

            $endObra = $prazoLancamento + $prazoObra;

            foreach ($curvaVendas as $mesVenda => $percentualVenda) {
                if ($percentualVenda <= 0) {
                    continue;
                }

                $s = $mesVenda + 1;
                $unidadesVendidas = $unidadesProduto * $percentualVenda / 100;

                $valorSinal = $precoProduto * $percentualSinal;
                $valorObra = $precoProduto * $percentualObra;
                $valorPos = $precoProduto * $percentualPos;

                if ($s <= $prazoLancamento) {
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
                    $dataRecebimento = $dataLancamento->copy()->addMonths($s - 1);
                    $chaveMes = $dataRecebimento->format('Y-m');

                    $ctx->recursosProprios[$chaveMes]['sinal'] =
                        ($ctx->recursosProprios[$chaveMes]['sinal'] ?? 0) + ($valorSinal * $unidadesVendidas);
                }

                $inicioObraCoorte = max($s, $prazoLancamento + 1);
                $numObraParcelas = $endObra - $inicioObraCoorte + 1;

                if ($numObraParcelas > 0 && $valorObra > 0) {
                    $parcelaObraNominal = $valorObra / $numObraParcelas;

                    for ($i = 0; $i < $numObraParcelas; $i++) {
                        $mesRecebimento = $inicioObraCoorte + $i;
                        $mesesPassados = $mesRecebimento - $s;

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

            $valorPosTotal = $precoProduto * $percentualPos * $unidadesProduto;
            $amortizacao = $valorPosTotal / $qtdParcelasPos;

            for ($k = 1; $k <= $qtdParcelasPos; $k++) {
                $saldoDevedor = $valorPosTotal - ($amortizacao * ($k - 1));

                $jurosMes = $saldoDevedor * $jurosMensalPos;
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
     * Modelo Próprio: entrada + parcelas mensais + balões anuais + balão na entrega.
     *
     * Os balões (anuais e entrega) são lidos de cada produto, permitindo que
     * diferentes tipologias tenham cronogramas de pagamento distintos.
     */
    private function preCalcularRecebiveisProprio(array $produtos, array $datas, array $params, ViabilidadeFluxoContext $ctx): void
    {
        $ctx->recursosProprios = [];

        $dataLancamento = $datas['dataLancamento'];
        $dataEntrega = $datas['dataEntrega'];

        $prazoLancamento = $params['mesesLancamento'];
        $prazoObra = $params['mesesObra'];
        $endObra = $prazoLancamento + $prazoObra;

        foreach ($produtos as $produto) {
            $curvaVendas = $this->curvaService->extrairCurva($produto['curva_vendas'] ?? null);
            $curvaVendas = $this->curvaService->normalizarCurva($curvaVendas);

            $unidadesProduto = $produto['quantidade_unidades'] ?? 1;
            $precoProduto = $produto['preco'] ?? 0;
            $fin = $produto['financeiro'];

            $percentualSinal = ($fin['sinal'] ?? 2) / 100;

            $baloesAnuais = $produto['baloes_anuais'] ?? [];
            $balaoEntregaModo = $produto['balao_entrega_modo'] ?? 'saldo_restante';

            foreach ($curvaVendas as $mesVenda => $percentualVenda) {
                if ($percentualVenda <= 0) {
                    continue;
                }

                $s = $mesVenda + 1;
                $unidadesVendidas = $unidadesProduto * $percentualVenda / 100;

                $valorUnitario = $precoProduto;
                $valorSinal = $valorUnitario * $percentualSinal;

                if ($s <= $prazoLancamento) {
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
                    $dataRecebimento = $dataLancamento->copy()->addMonths($s - 1);
                    $chaveMes = $dataRecebimento->format('Y-m');

                    $ctx->recursosProprios[$chaveMes]['sinal'] =
                        ($ctx->recursosProprios[$chaveMes]['sinal'] ?? 0) + ($valorSinal * $unidadesVendidas);
                }

                $valorRestante = $valorUnitario - $valorSinal;
                $valorJaAlocado = 0.0;

                foreach ($baloesAnuais as $balao) {
                    $mesBalao = (int) ($balao['mes'] ?? 12);
                    $percBalao = ($balao['percentual'] ?? 0) / 100;
                    $valorBalao = $valorUnitario * $percBalao;

                    $mesRecebimento = $s + $mesBalao - 1;
                    $dataRecebimento = $dataLancamento->copy()->addMonths($mesRecebimento - 1);
                    $chaveMes = $dataRecebimento->format('Y-m');

                    $ctx->recursosProprios[$chaveMes]['parcelas_obra'] =
                        ($ctx->recursosProprios[$chaveMes]['parcelas_obra'] ?? 0) + ($valorBalao * $unidadesVendidas);

                    $valorJaAlocado += $valorBalao;
                }

                if ($balaoEntregaModo === 'saldo_restante') {
                    $saldoRestante = max(0, $valorRestante - $valorJaAlocado);
                } else {
                    $saldoRestante = $valorUnitario * (float) $balaoEntregaModo;
                }

                if ($saldoRestante > 0) {
                    $dataRecebimento = $dataEntrega->copy();
                    $chaveMes = $dataRecebimento->format('Y-m');

                    $ctx->recursosProprios[$chaveMes]['parcelas_pos'] =
                        ($ctx->recursosProprios[$chaveMes]['parcelas_pos'] ?? 0) + ($saldoRestante * $unidadesVendidas);
                }

                $valorMensalidades = max(0, $valorRestante - $valorJaAlocado - $saldoRestante);

                if ($valorMensalidades > 0) {
                    $inicioObraCoorte = max($s, $prazoLancamento + 1);
                    $numParcelasMensais = $endObra - $inicioObraCoorte + 1;

                    if ($numParcelasMensais > 0) {
                        $parcelaMensalNominal = $valorMensalidades / $numParcelasMensais;

                        for ($i = 0; $i < $numParcelasMensais; $i++) {
                            $mesRecebimento = $inicioObraCoorte + $i;
                            $dataRecebimento = $dataLancamento->copy()->addMonths($mesRecebimento - 1);
                            $chaveMes = $dataRecebimento->format('Y-m');

                            $ctx->recursosProprios[$chaveMes]['parcelas_obra'] =
                                ($ctx->recursosProprios[$chaveMes]['parcelas_obra'] ?? 0) + ($parcelaMensalNominal * $unidadesVendidas);
                        }
                    }
                }
            }
        }

        ksort($ctx->recursosProprios);
    }

    /**
     * Aplica inadimplência/atraso sobre os recebíveis pré-calculados.
     *
     * Modos:
     * - atrasoMeses = 0: haircut direto → multiplica cada entrada por (1 - inadimplencia)
     * - atrasoMeses > 0: recuperação parcial → move inadimplencia% para atrasoMeses à frente,
     *   aplica taxaPerda sobre o valor atrasado
     *
     * Só aplica no perfil próprio.
     */
    private function aplicarInadimplencia(ViabilidadeFluxoContext $ctx, array $params): void
    {
        if ($ctx->perfil->isCef()) {
            return;
        }

        $inadimplencia = (float) ($params['inadimplencia'] ?? 0.0);
        $atrasoMeses = (int) ($params['atrasoMeses'] ?? 0);
        $taxaPerda = (float) ($params['taxaPerda'] ?? 0.0);

        if ($inadimplencia <= 0.0) {
            return;
        }

        $meses = array_keys($ctx->recursosProprios);
        sort($meses);

        if ($atrasoMeses <= 0) {
            foreach ($meses as $chaveMes) {
                $rp = &$ctx->recursosProprios[$chaveMes];
                foreach (['sinal', 'parcelas_obra', 'parcelas_pos'] as $campo) {
                    if (isset($rp[$campo])) {
                        $rp[$campo] *= (1 - $inadimplencia);
                    }
                }
            }

            return;
        }

        foreach ($meses as $chaveMes) {
            $rp = &$ctx->recursosProprios[$chaveMes];
            $totalMesAntes = ($rp['sinal'] ?? 0.0) + ($rp['parcelas_obra'] ?? 0.0) + ($rp['parcelas_pos'] ?? 0.0);

            if ($totalMesAntes <= 0.0) {
                continue;
            }

            $valorAtrasado = $totalMesAntes * $inadimplencia;
            $perdaDefinitiva = $valorAtrasado * $taxaPerda;
            $valorRecuperavel = $valorAtrasado - $perdaDefinitiva;

            $dataAtual = Carbon::parse($chaveMes . '-01');
            $dataDestino = $dataAtual->copy()->addMonths($atrasoMeses);
            $chaveDestino = $dataDestino->format('Y-m');

            $fator = $totalMesAntes > 0 ? (1 - $inadimplencia) : 1;
            foreach (['sinal', 'parcelas_obra', 'parcelas_pos'] as $campo) {
                if (isset($rp[$campo])) {
                    $rp[$campo] *= $fator;
                }
            }

            if ($valorRecuperavel > 0.0) {
                $ctx->parcelasAtrasadas[$chaveDestino] =
                    ($ctx->parcelasAtrasadas[$chaveDestino] ?? 0.0) + $valorRecuperavel;
            }
        }
    }

    /**
     * Inicializa caches para cálculos CEF (Recurso Terrenos e Medição de Obra)
     */
    private function inicializarCachesCef(array $dadosProdutos, array $datas, ViabilidadeFluxoContext $ctx): void
    {
        // Reset dos caches
        $ctx->vendasPorMes = [];
        $ctx->vendasAcumuladas = 0.0;
        $ctx->demandaAtingida = false;
        $ctx->mesDemandaAtingida = null;
        $ctx->medicaoObraAcumulada = 0.0;
        $ctx->curvaObraAcumulada = 0.0;
        $ctx->mesObraAtual = 0;
        $ctx->valorMedicaoTotal = 0.0;

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
        return Carbon::parse($mes.'-01');
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
        $percHouse = $params['percentualVendasHouse'] ?? 0.0;
        $taxaHouse = $params['comissaoHousePercentual'] ?? 0.0;
        $taxaImob = $params['comissaoImobiliariasPercentual'] ?? 0.0;
        $taxaMedia = ($percHouse * $taxaHouse) + ((1 - $percHouse) * $taxaImob);
        $comissaoBase = $valorVendido * $taxaMedia;
        $comissaoHouse = $valorVendido * $percHouse * $taxaHouse;
        $comissaoImob = $valorVendido * (1 - $percHouse) * $taxaImob;

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
        $dataAtual = Carbon::parse($mes.'-01');
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
        $dataAtual = Carbon::parse($mes.'-01');
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
        $curvaObra = $dadosProdutos['curvaObraAgregada'] ?? $this->agregarCurvaObra($params['mesesObra']);
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
        $dataAtual = Carbon::parse($mes.'-01');

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
            $curvaObra = $dadosProdutos['curvaObraAgregada'] ?? $this->agregarCurvaObra($params['mesesObra']);
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
        $receitaTotal = $dadosProdutos['vgvComCorrecao'] ?? $dadosProdutos['vgv'];

        return $receitaTotal > 0 ? ($totalCustoTerreno * $receitaMes) / $receitaTotal : 0;
    }

    private function calcularPagamentoParceriaTerreno(
        string $mes,
        float $receitaMes,
        array $datas,
        array $params,
        ViabilidadeFluxoContext $ctx
    ): float {
        $percentualParceria = (float) ($params['parceriaVgv'] ?? 0.0);
        if ($percentualParceria <= 0) {
            return 0.0;
        }

        $dataAtual = $this->parseMes($mes);
        if ($dataAtual->lessThan($datas['inicioObra'])) {
            return 0.0;
        }

        if ($ctx->parceriaVgvTotal <= 0) {
            return 0.0;
        }

        $restante = max(0.0, $ctx->parceriaVgvTotal - $ctx->parceriaVgvPago);
        if ($restante <= 0) {
            return 0.0;
        }

        $valorMes = max(0.0, $receitaMes * $percentualParceria);
        $pagar = min($restante, $valorMes);
        $ctx->parceriaVgvPago += $pagar;

        return $pagar;
    }

    private function calcularDespesasComerciaisMensais(string $mes, array $dadosProdutos, array $datas, array $params, ViabilidadeFluxoContext $ctx): array
    {
        $dataAtual = Carbon::parse($mes.'-01');
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
        $dataAtual = Carbon::parse($mes.'-01');
        $parcelamento = max(1, (int) ($params['parcelamentoComissaoMeses'] ?? 1));
        $taxaComissaoMedia = (($params['percentualVendasHouse'] ?? 0) * ($params['comissaoHousePercentual'] ?? 0)) +
            ((1 - ($params['percentualVendasHouse'] ?? 0)) * ($params['comissaoImobiliariasPercentual'] ?? 0));
        $percentualDesligamento = $params['pagamentoComissaoDesligamento'] ?? 0;
        $totalMes = 0;

        foreach ($ctx->vendasPorMes as $mesVenda => $unidadesVendaMes) {
            if ($unidadesVendaMes <= 0 || $mesVenda >= $mes) {
                continue;
            }
            $dataVenda = Carbon::parse($mesVenda.'-01');
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
        $dataAtual = Carbon::parse($mes.'-01');
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
        $dataAtual = Carbon::parse($mes.'-01');
        if (! $dataAtual->between($datas['dataLancamento'], $datas['fimObra'])) {
            return 0;
        }
        $totalSeguros = $this->calcularSegurosPorTipologia($dadosProdutos, $params);
        $mesesRateio = max(1, $params['mesesLancamento'] + $params['mesesObra']);

        return $totalSeguros / $mesesRateio;
    }

    private function calcularAssistenciaTecnicaMensal(string $mes, array $datas, array $params, float $baseAssistencia): float
    {
        $dataAtual = Carbon::parse($mes.'-01');
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
        $teveSaldoOperacionalNegativo = false;
        $teveSaldoFinanceiroNegativo = false;
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
            $dataAtual = Carbon::parse($mes.'-01');
            $valorOperacional = (float) ($linha['lucro'] ?? 0);
            $fluxoOperacionalTir[] = ['data' => $dataAtual->copy(), 'valor' => $valorOperacional];
            $saldoOperacional += $valorOperacional;
            if ($saldoOperacional < 0) {
                $teveSaldoOperacionalNegativo = true;
            }
            if ($paybackOperacionalMes === null && $teveSaldoOperacionalNegativo && $saldoOperacional >= 0) {
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
            if ($saldoFinanceiro < 0) {
                $teveSaldoFinanceiroNegativo = true;
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
            if ($paybackFinanceiroMes === null && $teveSaldoFinanceiroNegativo && $saldoFinanceiro >= 0) {
                $paybackFinanceiroMes = count($fluxoFinanceiroTir);
            }
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

    private function calcularDreCaixa(array $totais): array
    {
        $receitaTotal = (float) ($totais['receita'] ?? 0.0);
        $custoDireto = (float) ($totais['custo_direto'] ?? 0.0);
        $impostos = (float) ($totais['impostos'] ?? 0.0);
        $operacionais = (float) ($totais['custos_operacionais'] ?? 0.0);
        $financeiros = (float) ($totais['custos_financeiros'] ?? 0.0);
        $despesasTotal = $custoDireto + $impostos + $operacionais + $financeiros;
        $resultado = (float) ($totais['lucro'] ?? ($receitaTotal - $despesasTotal));
        $margem = $receitaTotal > 0 ? ($resultado / $receitaTotal) : 0.0;

        return [
            'receita_total' => round($receitaTotal, 2),
            'custo_direto_total' => round($custoDireto, 2),
            'impostos_total' => round($impostos, 2),
            'despesas_operacionais_total' => round($operacionais, 2),
            'despesas_financeiras_total' => round($financeiros, 2),
            'despesas_total' => round($despesasTotal, 2),
            'resultado_total' => round($resultado, 2),
            'margem_liquida_percentual' => round($margem * 100, 2),
        ];
    }

    private function calcularPonteReconcilicao(array $dreCaixa, array $dreGerencial, array $drePocMensalBlocos): array
    {
        $caixaReceita = (float) ($dreCaixa['receita_total'] ?? 0.0);
        $caixaResultado = (float) ($dreCaixa['resultado_total'] ?? 0.0);

        $dreReceitaBruta = (float) ($dreGerencial['receita_bruta'] ?? 0.0);
        $dreResultado = (float) ($dreGerencial['lucro_liquido_projeto'] ?? 0.0);
        $dreIr = (float) ($dreGerencial['irpj_csll'] ?? 0.0);
        $dreJurosPj = (float) ($dreGerencial['despesas_onerosas_bancos'] ?? 0.0);

        $pocResumo = is_array($drePocMensalBlocos['resumo'] ?? null) ? $drePocMensalBlocos['resumo'] : [];
        $pocReceita = (float) ($pocResumo['receita_reconhecida_poc_total'] ?? 0.0);
        $pocResultado = (float) ($pocResumo['resultado_contabil_total'] ?? 0.0);

        return [
            'caixa' => [
                'receita_total' => round($caixaReceita, 2),
                'resultado_total' => round($caixaResultado, 2),
            ],
            'dre_gerencial' => [
                'receita_bruta' => round($dreReceitaBruta, 2),
                'lucro_liquido' => round($dreResultado, 2),
                'irpj_csll' => round($dreIr, 2),
                'juros_pj' => round($dreJurosPj, 2),
            ],
            'dre_contabil_poc' => [
                'receita_reconhecida_poc_total' => round($pocReceita, 2),
                'resultado_contabil_total' => round($pocResultado, 2),
            ],
            'deltas' => [
                'receita_caixa_menos_receita_bruta' => round($caixaReceita - $dreReceitaBruta, 2),
                'resultado_caixa_menos_lucro_dre_gerencial' => round($caixaResultado - $dreResultado, 2),
                'lucro_dre_gerencial_menos_resultado_poc' => round($dreResultado - $pocResultado, 2),
            ],
            'principais_motivos' => [
                'Caixa considera entradas/saídas; DRE gerencial usa VGV e premissas de correção/juros para formar receita e apropria custos por regra.',
                'DRE contábil (POC) reconhece receita e custos conforme execução da obra, não conforme recebimento.',
                'IRPJ/CSLL e despesas onerosas (juros PJ) tendem a aparecer na DRE gerencial/financeira e não são necessariamente desembolso no mesmo timing do caixa operacional.',
            ],
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
                $resultado[$janela.'m'] = [
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

            $resultado[$janela.'m'] = [
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
     * Obtém a curva de obra baseada no prazo de obra da viabilidade via CurvaService.
     * Não depende mais de curva_obra por produto — usa a Curva S padrão do sistema.
     */
    private function agregarCurvaObra(int $mesesObra): array
    {
        return $this->curvaService->getCurvaObraParaPrazo($mesesObra);
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
