<?php

namespace App\Services\Tenant\Viabilidade;

use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * ViabilidadeUnificadoService - Service principal unificado
 * 
 * 4 Funções Principais:
 * 1. gerarFluxoMensal() - Orquestra tudo e gera fluxo de caixa
 * 2. calcularReceitas() - Todas as receitas de um mês
 * 3. calcularDespesas() - Todas as despesas de um mês
 * 4. calcularDre() - Consolida DRE final
 */
class ViabilidadeUnificadoService
{
    protected CurvaService $curvaService;
    protected ImpostosService $impostosService;

    // Cache para evitar recálculos
    private array $cacheRecursosPropriosMensais = [];
    private array $cacheVendasMensais = [];
    private array $cacheDadosProcessados = [];

    // Cache para CEF (Recurso Terrenos e Medição de Obra)
    private array $cacheVendasPorMes = [];
    private float $cacheVendasAcumuladas = 0;
    private float $cacheDemandaMinima = 0;
    private bool $cacheDemandaAtingida = false;
    private ?string $cacheMesDemandaAtingida = null;
    private float $cacheMedicaoObraAcumulada = 0;
    private float $cacheCurvaObraAcumulada = 0;
    private int $cacheMesObraAtual = 0;
    private float $cacheTotalRecursoTerrenos = 0;
    private float $cacheValorMedicaoTotal = 0;

    public function __construct(CurvaService $curvaService, ImpostosService $impostosService)
    {
        $this->curvaService = $curvaService;
        $this->impostosService = $impostosService;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * FUNÇÃO 1: GERAR FLUXO MENSAL
     * ═══════════════════════════════════════════════════════════════════════
     * Orquestra todo o cálculo e retorna fluxo de caixa completo
     */
    public function gerarFluxoMensal(
        int $terrenoId,
        $viabilidadeRef = null,
        ?array $customProdutos = null
    ): array {
        try {
            // 1. Buscar e processar dados
            $terreno = $this->buscarTerreno($terrenoId);
            $viabilidade = $this->buscarViabilidade($terrenoId, $viabilidadeRef);
            $params = $this->montarParametros($viabilidade);
            $dadosProdutos = $this->processarProdutos($terreno, $params, $customProdutos);

            if ($dadosProdutos['totalUnidades'] === 0 || $dadosProdutos['vgv'] === 0) {
                throw new Exception('Não foi possível calcular dados válidos dos produtos.');
            }

            // 2. Calcular datas dos períodos
            $datas = $this->calcularPeriodos($dadosProdutos['dataInicio'], $params);

            // 3. Pré-calcular recursos próprios (cache)
            $this->preCalcularRecursosProprios($dadosProdutos['produtos'], $datas, $params);

            // 4. Inicializar caches para CEF
            $this->inicializarCachesCef($dadosProdutos, $datas, $params);

            // 5. Iterar mês a mês
            $fluxo = [];
            $saldoAcumulado = 0;
            $fluxoTir = [];
            $totais = [
                'receita' => 0,
                'custo_direto' => 0,
                'impostos' => 0,
                'custos_operacionais' => 0,
                'custos_financeiros' => 0,
                'lucro' => 0
            ];

            $periodo = CarbonPeriod::create($datas['inicioIncorporacao'], '1 month', $datas['fimPos']);

            foreach ($periodo as $data) {
                $mes = $data->format('Y-m');

                // Calcular receitas e despesas do mês
                $receitas = $this->calcularReceitas($mes, $dadosProdutos, $datas, $params);
                $despesas = $this->calcularDespesas($mes, $receitas, $dadosProdutos, $datas, $params);

                // Consolidar resultado
                $lucroMes = $receitas['total'] - $despesas['total'];
                $saldoAcumulado += $lucroMes;

                // Obter unidades vendidas no mês atual
                $unidadesVendidasMes = ceil($this->cacheVendasPorMes[$mes] ?? 0);

                $fluxo[$mes] = [
                    'periodo' => $this->identificarPeriodo($data, $datas),
                    'receita_total' => round($receitas['total'], 2),
                    'receitas' => $receitas['detalhes'],
                    'despesas' => array_filter($despesas['detalhes'], fn($v) => abs($v) > 0.01),
                    'custos_totais' => round($despesas['total'], 2),
                    'lucro' => round($lucroMes, 2),
                    'saldo_acumulado' => round($saldoAcumulado, 2),
                    'unidades_vendidas' => round($unidadesVendidasMes, 2),
                ];

                $fluxoTir[] = ['data' => $data->copy(), 'valor' => $lucroMes];

                // Acumular totais detalhados
                $totais['receita'] += $receitas['total'];
                $totais['custo_direto'] += $despesas['categorias']['custo_direto'];
                $totais['impostos'] += $despesas['categorias']['impostos'];
                $totais['custos_operacionais'] += $despesas['categorias']['custos_operacionais'];
                $totais['custos_financeiros'] += $despesas['categorias']['custos_financeiros'];
                $totais['lucro'] += $lucroMes;
            }

            // 5. Calcular indicadores
            $indicadores = [
                'tir' => $this->calcularTir($fluxoTir),
                'exposicao_maxima' => collect($fluxo)->min('saldo_acumulado'),
                'margem_liquida' => $totais['receita'] > 0 ? ($totais['lucro'] / $totais['receita']) : 0,
            ];

            // 6. Calcular DRE consolidada
            $dre = $this->calcularDre($fluxo, $dadosProdutos, $params);

            return [
                'terreno' => $terreno,
                'vgv' => $dadosProdutos['vgvSemValorTerrenista'],
                'totalUnidades' => $dadosProdutos['totalUnidades'],
                'unidadesPermuta' => $dadosProdutos['permutas'],
                'areaConstruida' => $dadosProdutos['areaConstruida'],
                'custoTotal' => $dre['custo_total_projeto'],
                'produtos' => $dadosProdutos['produtos'],
                'dre_itens' => $dre,
                'indicadores' => array_merge($dre['indicadores'], $indicadores),
                'dados_produtos' => [
                    'total_unidades' => $dadosProdutos['totalUnidades'],
                    'unidades_permuta' => $dadosProdutos['permutas'],
                    'area_construida_total' => $dadosProdutos['areaConstruida'],
                ],
                'fluxo_mensal' => $fluxo,
                'totais' => $totais,
                'parametros_utilizados' => $params,
            ];

        } catch (Exception $e) {
            Log::error('Erro ao gerar fluxo mensal: ' . $e->getMessage(), [
                'terrenoId' => $terrenoId,
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception('Erro ao gerar fluxo mensal: ' . $e->getMessage());
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * FUNÇÃO 2: CALCULAR RECEITAS
     * ═══════════════════════════════════════════════════════════════════════
     * Calcula todas as receitas de um mês específico
     */
    public function calcularReceitas(
        string $mes,
        array $dadosProdutos,
        array $datas,
        array $params
    ): array {
        // Atualizar cache de vendas acumuladas do mês atual
        $vendasMes = $this->cacheVendasPorMes[$mes] ?? 0;
        $this->cacheVendasAcumuladas += $vendasMes;

        // 1. Recursos Próprios (do cache)
        $rp = $this->cacheRecursosPropriosMensais[$mes] ?? [
            'sinal' => 0,
            'parcelas_obra' => 0,
            'parcelas_pos' => 0,
            'juros' => 0,
            'correcao' => 0
        ];
        $totalRp = ($rp['sinal'] ?? 0) + ($rp['parcelas_obra'] ?? 0) + ($rp['parcelas_pos'] ?? 0);

        // 2. Recurso Terrenos (CEF)
        $rt = $this->calcularRecursoTerrenos($mes, $dadosProdutos, $datas, $params);

        // 3. Medição de Obra (CEF)
        $mo = $this->calcularMedicaoObra($mes, $dadosProdutos, $datas, $params);

        $total = $totalRp + $rt['valor'] + $mo['valor'];

        return [
            'total' => $total,
            'juros_correcao' => ($rp['juros'] ?? 0) + ($rp['correcao'] ?? 0),
            'detalhes' => [
                'Recursos Próprios' => round($totalRp, 2),
                'Recurso Terrenos' => round($rt['valor'], 2),
                'Medição Obra' => round($mo['valor'], 2),
            ],
        ];
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * FUNÇÃO 3: CALCULAR DESPESAS
     * ═══════════════════════════════════════════════════════════════════════
     * Calcula todas as despesas de um mês específico
     */
    public function calcularDespesas(
        string $mes,
        array $receitas,
        array $dadosProdutos,
        array $datas,
        array $params
    ): array {
        $dataAtual = Carbon::parse($mes . '-01');
        $periodo = $this->identificarPeriodo($dataAtual, $datas);
        $vgv = $dadosProdutos['vgv'];
        $custoObraTotal = $dadosProdutos['custoObraHabitacao'] + $dadosProdutos['custoInfraestrutura'];

        // 1. Custos Diretos (por período)
        $diretos = $this->calcularCustosDiretos($mes, $periodo, $datas, $params, $vgv, $custoObraTotal);

        // 2. Tributos
        $tributos = $this->impostosService->calcularTributosPorProduto(
            $receitas['total'],
            $receitas['juros_correcao'],
            $dadosProdutos['produtos'],
            $vgv,
            $params
        );

        // 3. Custos Operacionais
        $operacionais = $this->calcularCustosOperacionais($receitas['total'], $params);

        // 4. Custos Financeiros
        $financeiros = $receitas['total'] * ($params['percentualProdutosCef'] + $params['percentualOutrasDespesasFinanceiras']);

        // 5. Custo Terreno (proporcional à receita)
        $custoTerreno = $this->calcularCustoTerreno($mes, $receitas['total'], $dadosProdutos, $params);

        $total = $diretos['total'] + $tributos + $operacionais + $financeiros + $custoTerreno;

        return [
            'total' => $total,
            'detalhes' => array_merge($diretos['detalhes'], [
                'Tributos' => round($tributos, 2),
                'Operacional' => round($operacionais, 2),
                'Financeiro' => round($financeiros, 2),
                'Custo Terreno' => round($custoTerreno, 2),
            ]),
            'categorias' => [
                'custo_direto' => $diretos['total'] + $custoTerreno,
                'impostos' => $tributos,
                'custos_operacionais' => $operacionais,
                'custos_financeiros' => $financeiros,
            ],
        ];
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * FUNÇÃO 4: CALCULAR DRE
     * ═══════════════════════════════════════════════════════════════════════
     * Calcula a DRE consolidada a partir do fluxo mensal
     */
    public function calcularDre(array $fluxo, array $dadosProdutos, array $params): array
    {
        $vgv = $dadosProdutos['vgv'];
        $vgvSemTerrenista = $dadosProdutos['vgvSemValorTerrenista'];
        $vgvSemPermutas = $dadosProdutos['vgvSemUnidPermutas'];

        // Receitas
        $receitaTotalVendas = $vgvSemTerrenista;
        $jurosCorrecoes = $dadosProdutos['correcaoSobreVgv'];
        $receitaBruta = $receitaTotalVendas + $jurosCorrecoes;

        // Impostos
        $impostos = $this->impostosService->calcularImpostosDre($dadosProdutos['produtos'], $vgvSemTerrenista);
        $receitaLiquida = $receitaBruta - $impostos['total'];

        // Custos Diretos
        $custoTerreno = $params['compraTerreno'] + ($params['parceriaVgv'] * $dadosProdutos['vgvComCorrecao']) +
            ($dadosProdutos['permutas'] * ($dadosProdutos['custoCasaM2'] ?? 0)) +
            ($dadosProdutos['permutas'] * ($dadosProdutos['custoInfraM2'] ?? 0));

        $comissao = $params['percentualComissao'] * $receitaTotalVendas;
        $incorporacao = $params['percentualIncorporacao'] * $vgv;
        $infraCasas = $dadosProdutos['custoObraHabitacao'];
        $infraLotes = $dadosProdutos['custoInfraestrutura'] + $dadosProdutos['custoNaoIncidente'];
        $areaComum = $params['custoAreaComum'];
        $contrapartidas = $params['percentualContrapartidas'] * $vgv;
        $canteiroMensal = $params['canteiroMensal'];
        $moAdministrativa = $params['moAdministrativa'];
        $seguros = $params['percentualSeguros'] * $vgv;

        $custoTotalObra = $infraCasas + $infraLotes + $areaComum + $contrapartidas + $canteiroMensal;
        $assistenciaTecnica = $params['percentualAssistenciaTecnica'] * $custoTotalObra;

        // Juros PJ
        $jurosPJ = $this->impostosService->calcularJurosPJ($custoTotalObra, $params['mesesObra']);

        $custosDiretosTotal = $custoTerreno + $comissao + $incorporacao + $infraCasas + $infraLotes +
            $areaComum + $contrapartidas + $canteiroMensal + $moAdministrativa + $seguros + $assistenciaTecnica;

        $lucroBruto = $receitaLiquida - $custosDiretosTotal;

        // Despesas Operacionais
        $despesasComerciais = $params['percentualDespesasComerciais'] * $vgvSemPermutas;
        $marketing = $params['percentualMarketing'] * $vgvSemPermutas;
        $itbiIptu = $params['custoItbiIptu'] * $vgvSemPermutas;
        $registro = $params['custoRegistro'] * $dadosProdutos['totalUnidadesConstrutora'];
        $txMedicao = 24000 + ($params['custoMedicaoContratacao'] * $params['mesesObra']);
        $contratosCef = $params['custoContratosCef'] * $dadosProdutos['totalUnidadesConstrutora'];
        $produtosCef = $params['percentualProdutosCef'] * $vgvSemPermutas;

        $despesasOperacionaisTotal = $despesasComerciais + $marketing + $itbiIptu + $registro +
            $txMedicao + $contratosCef + $produtosCef;

        $ebitda = $lucroBruto - $despesasOperacionaisTotal;

        // Despesas Financeiras
        $outrasDespFinanceiras = $params['percentualOutrasDespesasFinanceiras'] * $receitaTotalVendas;
        $despesasOnerosas = $jurosPJ['juros_totais'];

        $ebit = $ebitda - $outrasDespFinanceiras - $despesasOnerosas;

        // Lucro Líquido
        $irpjCsll = $impostos['total_ir_csll'];
        $lucroLiquido = $ebit - $irpjCsll;

        // Custo Total do Projeto
        $custoTotalProjeto = $custosDiretosTotal + $despesasOperacionaisTotal +
            $outrasDespFinanceiras + $despesasOnerosas + $irpjCsll + $impostos['total'];

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
            'infra_casas' => round($infraCasas, 2),
            'infra_lotes' => round($infraLotes, 2),
            'area_comum' => round($areaComum, 2),
            'contrapartidas' => round($contrapartidas, 2),
            'canteiro_mensal' => round($canteiroMensal, 2),
            'mo_administrativa' => round($moAdministrativa, 2),
            'seguros' => round($seguros, 2),
            'assistencia_tecnica' => round($assistenciaTecnica, 2),
            'custo_total_obra' => round($custoTotalObra, 2),
            'lucro_bruto' => round($lucroBruto, 2),
            'despesas_comerciais' => round($despesasComerciais, 2),
            'marketing' => round($marketing, 2),
            'itbi_iptu' => round($itbiIptu, 2),
            'registro' => round($registro, 2),
            'tx_medicao_contratacao' => round($txMedicao, 2),
            'contratos_caixa' => round($contratosCef, 2),
            'produtos_caixa' => round($produtosCef, 2),
            'ebitda' => round($ebitda, 2),
            'outras_despesas_financeiras' => round($outrasDespFinanceiras, 2),
            'despesas_onerosas_bancos' => round($despesasOnerosas, 2),
            'juros_pj' => round($jurosPJ['juros_totais'], 2),
            'ebit' => round($ebit, 2),
            'irpj_csll' => round($irpjCsll, 2),
            'lucro_liquido_projeto' => round($lucroLiquido, 2),
            'custo_total_projeto' => round($custoTotalProjeto, 2),
            'indicadores' => [
                'vgv_total' => round($receitaTotalVendas, 2),
                'lucro_liquido' => round($lucroLiquido, 2),
                'margem_liquida_percentual' => $receitaTotalVendas > 0 ? round(($lucroLiquido / $receitaTotalVendas) * 100, 2) : 0,
                'roi_percentual' => $custosDiretosTotal > 0 ? round(($lucroLiquido / $custosDiretosTotal) * 100, 2) : 0,
                'total_custos_diretos' => round($custosDiretosTotal, 2),
                'custo_total_projeto' => round($custoTotalProjeto, 2),
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MÉTODOS AUXILIARES PRIVADOS
    // ═══════════════════════════════════════════════════════════════════════

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
                    'curva_vendas'
                ]),
            ])
            ->findOrFail($terrenoId);
    }

    private function buscarViabilidade(int $terrenoId, $viabilidadeRef): Viabilidade
    {
        if ($viabilidadeRef instanceof Viabilidade)
            return $viabilidadeRef;
        if (is_numeric($viabilidadeRef))
            return Viabilidade::findOrFail($viabilidadeRef);

        return Viabilidade::where('terreno_id', $terrenoId)->latest()->first()
            ?? new Viabilidade(['terreno_id' => $terrenoId]);
    }

    private function montarParametros(?Viabilidade $v): array
    {
        $d = config('viabilidade.defaults');
        $p = config('viabilidade.prazos');

        return [
            'percentualImpostos' => (($v->pis_cofins ?? $d['pis_cofins']) + ($v->iss ?? $d['iss']) + ($v->outros_impostos ?? $d['outros_impostos'])) / 100,
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
            'percentualMarketing' => ($v->marketing ?? $d['marketing']) / 100,
            'custoItbiIptu' => ($v->itbi_iptu ?? $d['itbi_iptu']) / 100,
            'custoRegistro' => $v->registro ?? $d['registro'],
            'custoMedicaoContratacao' => $v->medicao_contratacao ?? $d['medicao_contratacao'],
            'custoContratosCef' => $v->contratos_cef ?? $d['contratos_cef'],
            'percentualProdutosCef' => ($v->produtos_cef ?? $d['produtos_cef']) / 100,
            'percentualOutrasDespesasFinanceiras' => ($v->outras_despesas_financeiras ?? $d['outras_despesas_financeiras']) / 100,
            'mesesObra' => (int) ($v->prazo_obra ?? $d['prazo_obra']),
            'mesesIncorporacao' => $p['meses_incorporacao'],
            'mesesLancamento' => $p['meses_lancamento'],
            'mesesEntrega' => $p['meses_entrega'],
            'mesesPosObra' => $p['meses_pos_obra'],
            'variavelCorrecao' => $p['variavel_correcao'],
            'compraTerreno' => $v->compra_terreno ?? 0,
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
                if (isset($cp['id']))
                    $customMap[$cp['id']] = $cp;
            }
        }

        foreach ($terreno->terrenoProdutos as $terrenoProduto) {
            if (!$terrenoProduto?->produto)
                continue;

            $produto = $terrenoProduto->produto;
            $cp = $customMap[$terrenoProduto->id] ?? [];

            $unidades = $cp['unidades'] ?? $terrenoProduto->unidades ?? 1;
            $valor = $cp['valor'] ?? $terrenoProduto->valor ?? 0;
            $permutas = $cp['permuta'] ?? $terrenoProduto->permuta ?? 0;
            $pgtoPorLote = $cp['pgto_por_lote'] ?? $terrenoProduto->pgto_por_lote ?? 0;
            // Avaliação lotes CEF: usa customProdutos, ou calcula 15% do valor do produto
            $percentualAvaliacaoCef = config('viabilidade.defaults.avaliacao_lotes_cef', 15) / 100;
            $avaliacaoLotesCef = $cp['avaliacao_lotesCef'] ?? ($valor * $percentualAvaliacaoCef);
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
                'tipo_produto' => $this->curvaService->determinarTipoProduto([$terrenoProduto]),
                'imposto_tributos' => ($produto->imposto_tributos ?? 0) / 100,
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
        if ($data < $datas['dataLancamento'])
            return 'Incorporação';
        if ($data->between($datas['dataLancamento'], $datas['fimLancamento']))
            return 'Lançamento';
        if ($data->between($datas['inicioObra'], $datas['fimObra']))
            return 'Obra';
        if ($data->format('Y-m') === $datas['dataEntrega']->format('Y-m'))
            return 'Entrega';
        if ($data >= $datas['inicioPos'])
            return 'Pós-Obra';
        return 'Transição';
    }

    /**
     * Pré-calcula recursos próprios baseado na lógica de calculateMonthlyReceipts
     * 
     * - Sinal: dividido em parcelas durante o lançamento (se venda no lançamento)
     * - Obra: parcelas com correção composta pelo tempo desde a venda
     * - Pós-chave: amortização + juros + correção sobre saldo devedor decrescente
     */
    private function preCalcularRecursosProprios(array $produtos, array $datas, array $params): void
    {
        $this->cacheRecursosPropriosMensais = [];

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
            $tipoProd = $produto['tipo_produto'];
            $mesesCurva = $this->curvaService->getMesesCurvaPadrao($tipoProd);
            $curvaVendas = $this->curvaService->getCurvaVendas($mesesCurva, $tipoProd);

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
                if ($percentualVenda <= 0)
                    continue;

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

                        $this->cacheRecursosPropriosMensais[$chaveMes]['sinal'] =
                            ($this->cacheRecursosPropriosMensais[$chaveMes]['sinal'] ?? 0) + ($parcelaSinal * $unidadesVendidas);
                    }
                } else {
                    // Venda após lançamento: sinal recebido no mês da venda
                    $dataRecebimento = $dataLancamento->copy()->addMonths($s - 1);
                    $chaveMes = $dataRecebimento->format('Y-m');

                    $this->cacheRecursosPropriosMensais[$chaveMes]['sinal'] =
                        ($this->cacheRecursosPropriosMensais[$chaveMes]['sinal'] ?? 0) + ($valorSinal * $unidadesVendidas);
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

                        $this->cacheRecursosPropriosMensais[$chaveMes]['parcelas_obra'] =
                            ($this->cacheRecursosPropriosMensais[$chaveMes]['parcelas_obra'] ?? 0) + ($parcelaAjustada * $unidadesVendidas);
                        $this->cacheRecursosPropriosMensais[$chaveMes]['correcao'] =
                            ($this->cacheRecursosPropriosMensais[$chaveMes]['correcao'] ?? 0) + ($correcaoMes * $unidadesVendidas);
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

                $this->cacheRecursosPropriosMensais[$chaveMes]['parcelas_pos'] =
                    ($this->cacheRecursosPropriosMensais[$chaveMes]['parcelas_pos'] ?? 0) + $pagamentoMes;
                $this->cacheRecursosPropriosMensais[$chaveMes]['juros'] =
                    ($this->cacheRecursosPropriosMensais[$chaveMes]['juros'] ?? 0) + $jurosMes;
                $this->cacheRecursosPropriosMensais[$chaveMes]['correcao'] =
                    ($this->cacheRecursosPropriosMensais[$chaveMes]['correcao'] ?? 0) + $correcaoMes;
            }
        }
    }

    /**
     * Inicializa caches para cálculos CEF (Recurso Terrenos e Medição de Obra)
     */
    private function inicializarCachesCef(array $dadosProdutos, array $datas, array $params): void
    {
        // Reset dos caches
        $this->cacheVendasPorMes = [];
        $this->cacheVendasAcumuladas = 0;
        $this->cacheDemandaAtingida = false;
        $this->cacheMesDemandaAtingida = null;
        $this->cacheMedicaoObraAcumulada = 0;
        $this->cacheCurvaObraAcumulada = 0;
        $this->cacheMesObraAtual = 0;
        $this->cacheTotalRecursoTerrenos = 0;

        // Calcular demanda mínima total
        $this->cacheDemandaMinima = 0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            $this->cacheDemandaMinima += $produto['demanda_minCef'] ?? 0;
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
        $this->cacheValorMedicaoTotal = max(0, ($vgv * 0.80) - $totalRecursoTerrenos);

        // Pré-calcular vendas por mês (unidades vendidas)
        $dataLancamento = $datas['dataLancamento'];

        foreach ($dadosProdutos['produtos'] as $produto) {
            $tipoProd = $produto['tipo_produto'] ?? '2_dorm';
            $mesesCurva = $this->curvaService->getMesesCurvaPadrao($tipoProd);
            $curvaVendas = $this->curvaService->getCurvaVendas($mesesCurva, $tipoProd);
            $unidadesProduto = $produto['quantidade_unidades'] ?? 0;

            foreach ($curvaVendas as $mesIndex => $percentualVenda) {
                if ($percentualVenda <= 0)
                    continue;

                $dataVenda = $dataLancamento->copy()->addMonths($mesIndex);
                $chaveMes = $dataVenda->format('Y-m');

                $unidadesVendidas = $unidadesProduto * ($percentualVenda / 100);
                $this->cacheVendasPorMes[$chaveMes] = ($this->cacheVendasPorMes[$chaveMes] ?? 0) + $unidadesVendidas;
            }
        }
    }

    private function adicionarRecursoProprio(string $mes, string $tipo, float $valor, array $fin, string $origem): void
    {
        $this->cacheRecursosPropriosMensais[$mes][$tipo] = ($this->cacheRecursosPropriosMensais[$mes][$tipo] ?? 0) + $valor;
    }

    private function adicionarRecursoProprioDetalhado(string $mes, string $tipo, float $valor, float $juros, float $correcao): void
    {
        $this->cacheRecursosPropriosMensais[$mes][$tipo] = ($this->cacheRecursosPropriosMensais[$mes][$tipo] ?? 0) + $valor;
        $this->cacheRecursosPropriosMensais[$mes]['juros'] = ($this->cacheRecursosPropriosMensais[$mes]['juros'] ?? 0) + $juros;
        $this->cacheRecursosPropriosMensais[$mes]['correcao'] = ($this->cacheRecursosPropriosMensais[$mes]['correcao'] ?? 0) + $correcao;
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
    private function calcularRecursoTerrenos(string $mes, array $dadosProdutos, array $datas, array $params): array
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
            $curvaVendas = $produto['curva_vendas'] ?? [];

            if (empty($curvaVendas)) {
                $tipoProd = $produto['tipo_produto'] ?? '2_dorm';
                $mesesCurva = $this->curvaService->getMesesCurvaPadrao($tipoProd);
                $curvaVendas = $this->curvaService->getCurvaVendas($mesesCurva, $tipoProd);
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
    private function calcularMedicaoObra(string $mes, array $dadosProdutos, array $datas, array $params): array
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

        // Obter percentual da curva S para este mês e acumular
        $percObraMes = $this->curvaService->getPercentualCustoObra($params['mesesObra'], $mesObraAtual);

        // Atualizar acumulado de obra apenas se for um novo mês de obra (proteção contra chamadas repetidas, embora o fluxo seja sequencial)
        // No contexto atual, como é sequencial e cacheMesObraAtual começa em 0, podemos só comparar
        if ($mesObraAtual > $this->cacheMesObraAtual) {
            $this->cacheCurvaObraAcumulada += ($percObraMes / 100);
            $this->cacheMesObraAtual = $mesObraAtual;
        }

        // 1. Valor da Medição Teórica Acumulada (Física)
        $medicaoTeoricaAcumulada = $this->cacheValorMedicaoTotal * $this->cacheCurvaObraAcumulada;

        // 2. Percentual de Vendas Acumulado
        $totalUnidades = $dadosProdutos['totalUnidades'];
        $percVendasAcumulado = $totalUnidades > 0 ? $this->cacheVendasAcumuladas / $totalUnidades : 0;

        // 3. Valor da Medição Vendida Acumulada (Financeira)
        $medicaoVendidaAcumulada = $medicaoTeoricaAcumulada * $percVendasAcumulado;

        // 4. Valor a Receber no Mês (Diferença do acumulado anterior)
        // cacheMedicaoObraAcumulada armazena o "Total Recebido Até Agora"
        $valorReceberMes = max(0, $medicaoVendidaAcumulada - $this->cacheMedicaoObraAcumulada);

        // Atualizar o acumulado recebido
        $this->cacheMedicaoObraAcumulada += $valorReceberMes;

        return ['valor' => round($valorReceberMes, 2)];
    }

    private function calcularCustosDiretos(string $mes, string $periodo, array $datas, array $params, float $vgv, float $custoObraTotal): array
    {
        $custos = [];
        $dataAtual = Carbon::parse($mes . '-01');

        // Incorporação
        $custoIncorp = $vgv * $params['percentualIncorporacao'];
        $prazoIncorp = $params['mesesIncorporacao'] + $params['mesesLancamento'];
        $valorIncorpMes = (($custoIncorp * 0.55) * 0.8) / $prazoIncorp;

        if ($periodo === 'Incorporação' || ($periodo === 'Lançamento' && $mes === $datas['dataLancamento']->format('Y-m'))) {
            $custos['Incorporação'] = round($valorIncorpMes, 2);
        }

        // Obra
        if ($periodo === 'Obra') {
            $mesObraIndex = (int) $datas['inicioObra']->diffInMonths($dataAtual) + 1;
            $percentualMes = $this->curvaService->getPercentualCustoObra($params['mesesObra'], $mesObraIndex);
            $custos['Obra'] = round($custoObraTotal * ($percentualMes / 100), 2);
            $custos['Canteiro'] = round($params['canteiroMensal'] / $params['mesesObra'], 2);
            $custos['Área Comum'] = round($params['custoAreaComum'] / $params['mesesObra'], 2);
            $custos['M.O. Administrativa'] = round($params['moAdministrativa'] / $params['mesesObra'], 2);

            if ($mes === $datas['inicioObra']->format('Y-m')) {
                $custos['Medição/Contratação'] = $params['custoMedicaoContratacao'];
                $custos['Contratos CEF'] = $params['custoContratosCef'];
            }
        }

        // Entrega
        if ($periodo === 'Entrega') {

        }

        // Pós-Obra
        if ($periodo === 'Pós-Obra') {
            $custos['Assistência Técnica'] = round(($vgv * $params['percentualAssistenciaTecnica']) / $params['mesesPosObra'], 2);
        }

        return [
            'detalhes' => $custos,
            'total' => array_sum($custos),
        ];
    }

    private function calcularCustosOperacionais(float $receita, array $params): float
    {
        return $receita * ($params['percentualDespesasComerciais'] + $params['percentualMarketing'] + $params['percentualSeguros']);
    }

    private function calcularCustoTerreno(string $mes, float $receitaMes, array $dadosProdutos, array $params): float
    {
        $totalCustoTerreno = ($dadosProdutos['permutas'] * ($dadosProdutos['produtos'][0]['preco'] ?? 0)) + $params['compraTerreno'];
        $receitaTotal = $dadosProdutos['vgv'];
        return $receitaTotal > 0 ? ($totalCustoTerreno * $receitaMes) / $receitaTotal : 0;
    }

    private function calcularTir(array $fluxo, float $estimativa = 0.1): float
    {
        $temPositivo = false;
        $temNegativo = false;
        foreach ($fluxo as $item) {
            if ($item['valor'] > 0)
                $temPositivo = true;
            if ($item['valor'] < 0)
                $temNegativo = true;
        }
        if (!$temPositivo || !$temNegativo)
            return 0;

        $taxa = $estimativa;
        for ($i = 0; $i < 100; $i++) {
            $f = $df = 0;
            foreach ($fluxo as $t => $item) {
                $f += $item['valor'] / pow(1 + $taxa, $t);
                $df -= $t * $item['valor'] / pow(1 + $taxa, $t + 1);
            }
            if (abs($df) < 0.00001)
                break;
            $proximaTaxa = $taxa - ($f / $df);
            if (abs($proximaTaxa - $taxa) < 0.00001) {
                return pow(1 + $proximaTaxa, 12) - 1;
            }
            $taxa = $proximaTaxa;
        }
        return 0;
    }
}
