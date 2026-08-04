<?php

namespace App\Services\Tenant\Viabilidade\v1\Calculos;

use App\Models\Tenant\Terreno;
use App\Services\Tenant\Viabilidade\v1\CurvaService;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeFluxoContext;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeSnapshotService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class FluxoMensalCalculator
{
    public function __construct(
        private readonly CurvaService $curvaService,
        private readonly ReceitasCalculator $receitasCalculator,
        private readonly DespesasCalculator $despesasCalculator,
        private readonly DreCalculator $dreCalculator,
        private readonly IndicadoresCalculator $indicadoresCalculator,
        private readonly PocCalculator $pocCalculator,
        private readonly ProdutosProcessor $produtosProcessor,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @param  list<array<string, mixed>>|null  $customProdutos
     * @return array<string, mixed>
     */
    public function calcular(Terreno $terreno, array $params, ?array $customProdutos): array
    {
        $dadosProdutos = $this->produtosProcessor->processar($terreno, $params, $customProdutos);
        $params = $this->produtosProcessor->mesclarParametros($params, $dadosProdutos);

        $validacaoCurvas = $this->curvaService->validarCurvasObrigatorias($dadosProdutos['produtos']);
        if (! $validacaoCurvas['valid']) {
            throw new \Exception(
                'Curvas obrigatórias não preenchidas nos produtos: '.implode(', ', $validacaoCurvas['faltando'])
            );
        }

        if ($dadosProdutos['totalUnidades'] === 0 || $dadosProdutos['vgv'] === 0) {
            throw new \Exception('Não foi possível calcular dados válidos dos produtos.');
        }

        $dadosProdutos['curvaObraAgregada'] = $this->agregarCurvaObra((int) $params['mesesObra']);
        $dadosProdutos['curvaFinanceiraMedicaoAgregada'] = $this->curvaService->getCurvaFinanceiraMedicaoParaPrazo(
            (int) $params['mesesObra'],
            (float) ($params['obraAteLancamento'] ?? 0.0),
        );
        $curveWarnings = $this->curvaService->pullWarnings();
        $datas = $this->calcularPeriodos($dadosProdutos['dataInicio'], $params);

        $ctx = new ViabilidadeFluxoContext;
        $ctx->perfil = $params['perfilFinanciamento'];

        $this->preCalcularRecebiveis($dadosProdutos['produtos'], $datas, $params, $ctx);

        if ($ctx->perfil->isCef()) {
            $this->inicializarCachesCef($dadosProdutos, $datas, $ctx);
        }

        $ctx->parceriaVgvTotal = 0.0;
        $ctx->parceriaVgvPago = 0.0;

        $fluxo = [];
        $saldoAcumulado = 0.0;
        $saldoSemCefAcumulado = 0.0;
        $totalJurosCorrecoes = 0.0;
        $fluxoTir = [];
        $fluxoTirSemCef = [];
        $totais = [
            'receita' => 0.0,
            'custo_direto' => 0.0,
            'impostos' => 0.0,
            'custos_operacionais' => 0.0,
            'custos_financeiros' => 0.0,
            'lucro' => 0.0,
        ];

        $periodo = CarbonPeriod::create($datas['inicioIncorporacao'], '1 month', $datas['fimPos']);

        $ctxReceitas = clone $ctx;
        $periodoReceitas = CarbonPeriod::create($datas['inicioIncorporacao'], '1 month', $datas['fimPos']);
        $mesesComReceitas = 0;
        $totalJurosCorrecoesPrevistos = 0.0;

        /** @var list<Carbon> $periodoReceitasLista */
        $periodoReceitasLista = $periodoReceitas->toArray();
        foreach ($periodoReceitasLista as $dataReceita) {
            $mesReceita = $dataReceita->format('Y-m');
            $receitasMes = $this->receitasCalculator->calcular($mesReceita, $dadosProdutos, $datas, $params, $ctxReceitas);
            $totalJurosCorrecoesPrevistos += (float) $receitasMes['juros_correcao'];

            if ((float) $receitasMes['total'] > 0.01) {
                $mesesComReceitas++;
            }
        }

        $dadosProdutos['correcaoSobreVgv'] = $totalJurosCorrecoesPrevistos;
        $dadosProdutos['vgvComCorrecao'] = ($dadosProdutos['vgvSemValorTerrenista'] ?? 0) + $totalJurosCorrecoesPrevistos;
        $ctx->parceriaVgvTotal = max(0.0, ((float) ($params['parceriaVgv'] ?? 0.0)) * ((float) $dadosProdutos['vgvComCorrecao']));

        $outrasDespesasFinanceirasTotal = (float) ($params['outrasDespesasFinanceirasTotal'] ?? 0.0);
        $percentualOutrasDespesasFinanceiras = (float) ($params['percentualOutrasDespesasFinanceiras'] ?? 0.0);
        if ($percentualOutrasDespesasFinanceiras > 0) {
            $baseOutrasDespesasFinanceiras = max(0.0, (float) ($dadosProdutos['vgvSemValorTerrenista'] ?? $dadosProdutos['vgvSemUnidPermutas'] ?? 0.0));
            $outrasDespesasFinanceirasTotal = $baseOutrasDespesasFinanceiras * $percentualOutrasDespesasFinanceiras;
            $params['outrasDespesasFinanceirasTotal'] = $outrasDespesasFinanceirasTotal;
        }
        if ($outrasDespesasFinanceirasTotal > 0) {
            $params['mesesComReceitas'] = $mesesComReceitas;
            $params['outrasDespesasFinanceirasMensal'] = $mesesComReceitas > 0 ? ($outrasDespesasFinanceirasTotal / $mesesComReceitas) : 0.0;
        }

        $totalUnidadesComercializaveis = max(1.0, (float) ($dadosProdutos['totalUnidadesConstrutora'] ?? $dadosProdutos['totalUnidades'] ?? 1.0));
        $unidadesVendidasAcumuladasFluxo = 0.0;
        $fracaoVendasCarregada = 0.0;

        /** @var list<Carbon> $periodoLista */
        $periodoLista = $periodo->toArray();
        foreach ($periodoLista as $data) {
            $mes = $data->format('Y-m');

            $receitas = $this->receitasCalculator->calcular($mes, $dadosProdutos, $datas, $params, $ctx);
            $despesas = $this->despesasCalculator->calcular($mes, $receitas, $dadosProdutos, $datas, $params, $ctx);

            $lucroMes = $receitas['total'] - $despesas['total'];
            $saldoAcumulado += $lucroMes;

            $receitaRpMes = $receitas['detalhes']['recursos_proprios']['total_recursos_proprios'] ?? 0.0;
            $lucroSemCefMes = $receitaRpMes - $despesas['total'];
            $saldoSemCefAcumulado += $lucroSemCefMes;
            $vendasMesBrutas = max(0.0, (float) ($ctx->vendasPorMes[$mes] ?? 0.0));
            $vendasMesComCarry = $vendasMesBrutas + $fracaoVendasCarregada;
            $unidadesVendidasMes = max(0.0, floor($vendasMesComCarry + 1e-9));
            $estoqueRemanescenteMes = max(0.0, $totalUnidadesComercializaveis - $unidadesVendidasAcumuladasFluxo);
            $unidadesVendidasMes = min($unidadesVendidasMes, $estoqueRemanescenteMes);
            $fracaoVendasCarregada = max(0.0, $vendasMesComCarry - $unidadesVendidasMes);
            $unidadesVendidasAcumuladasFluxo += $unidadesVendidasMes;

            $fluxo[$mes] = [
                'periodo' => $this->identificarPeriodo($data, $datas),
                'receitas' => array_merge($receitas['detalhes'], ['total' => $receitas['total']]),
                'despesas' => array_merge($despesas['detalhes'], ['total' => $despesas['total']]),
                'saldo_mes' => round($lucroMes, 2),
                'saldo_acumulado_mes' => round($saldoAcumulado, 2),
                'unidades_vendidas' => $unidadesVendidasMes,
            ];

            $fluxoTir[] = ['data' => $data->copy(), 'valor' => $saldoAcumulado];
            $fluxoTirSemCef[] = ['data' => $data->copy(), 'valor' => $saldoSemCefAcumulado];

            $totais['receita'] += $receitas['total'];
            $totais['custo_direto'] += $despesas['categorias']['custo_direto'];
            $totais['impostos'] += $despesas['categorias']['impostos'];
            $totais['custos_operacionais'] += $despesas['categorias']['custos_operacionais'];
            $totais['custos_financeiros'] += $despesas['categorias']['custos_financeiros'];
            $totais['lucro'] += $lucroMes;

            $totalJurosCorrecoes += (float) $receitas['juros_correcao'];
        }

        $dadosProdutos['correcaoSobreVgv'] = $totalJurosCorrecoes;
        $dadosProdutos['vgvComCorrecao'] = ($dadosProdutos['vgvSemValorTerrenista'] ?? 0) + $totalJurosCorrecoes;

        $params['dataAntecipacaoPj'] = $ctx->mesDemandaAtingida !== null
            ? Carbon::parse($ctx->mesDemandaAtingida.'-01')->startOfMonth()
            : $datas['inicioObra']->copy()->startOfMonth();
        [$fluxoFinanceiro, $indicadoresFinanceiros] = $this->indicadoresCalculator->calcularIndicadoresFinanceiros($fluxo, $datas, $params, $dadosProdutos);
        $indicadoresVso = $this->indicadoresCalculator->calcularIndicadoresVso($fluxo, $dadosProdutos);
        $indicadoresVsoJanelas = $this->indicadoresCalculator->calcularIndicadoresVsoJanelas($fluxo, $dadosProdutos);

        $tirOperacionalAnual = $this->indicadoresCalculator->calcularTir($fluxoTir);
        $tirSemCefAnual = $this->indicadoresCalculator->calcularTir($fluxoTirSemCef);
        $tirOperacionalMensal = ($tirOperacionalAnual !== null && $tirOperacionalAnual > -1.0)
            ? (pow(1 + $tirOperacionalAnual, 1 / 12) - 1)
            : null;
        $tirSemCefMensal = ($tirSemCefAnual !== null && $tirSemCefAnual > -1.0)
            ? (pow(1 + $tirSemCefAnual, 1 / 12) - 1)
            : null;

        $indicadores = [
            'tir_operacional' => $tirOperacionalAnual,
            'tir_operacional_am_percentual' => $tirOperacionalMensal !== null ? round($tirOperacionalMensal * 100, 2) : null,
            'tir_operacional_aa_percentual' => $tirOperacionalAnual !== null ? round($tirOperacionalAnual * 100, 2) : null,
            'tir_sem_cef' => $tirSemCefAnual,
            'tir_sem_cef_am_percentual' => $tirSemCefMensal !== null ? round($tirSemCefMensal * 100, 2) : null,
            'tir_sem_cef_aa_percentual' => $tirSemCefAnual !== null ? round($tirSemCefAnual * 100, 2) : null,
            'exposicao_maxima_operacional' => collect($fluxo)->min('saldo_acumulado_mes'),
            'margem_liquida' => $totais['receita'] > 0 ? ($totais['lucro'] / $totais['receita']) : 0.0,
        ];

        $dre = $this->dreCalculator->calcular($fluxo, $dadosProdutos, $params);
        $dreContabilPoc = $this->pocCalculator->calcularDreContabilPoc($fluxo, $dre, $dadosProdutos);
        $dreContabilPocMensal = $this->pocCalculator->calcularQuadroPocMensal($fluxo, $dre, $dadosProdutos);
        $dreContabilPocMensalBlocos = $this->pocCalculator->calcularQuadroPocMensalPorBlocos($fluxo, $dre, $dadosProdutos);
        $dreCaixa = $this->pocCalculator->calcularDreCaixa($totais);
        $ponteReconcilicao = $this->pocCalculator->calcularPonteReconcilicao($dreCaixa, $dre, $dreContabilPocMensalBlocos);
        $reconciliation = $this->buildReconciliation(
            $totais,
            $fluxo,
            $dadosProdutos,
            $dre,
            $dreContabilPoc,
            $indicadoresFinanceiros,
        );

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
            'reconciliation' => $reconciliation,
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
            'calculation_engine_version' => ViabilidadeSnapshotService::ENGINE_VERSION,
            'warnings' => array_values(array_unique(array_merge(
                $curveWarnings,
                $reconciliation['warnings'],
            ))),
        ];
    }

    /**
     * @param  array<string, float>  $totais
     * @param  array<string, array<string, mixed>>  $fluxo
     * @param  array<string, mixed>  $dadosProdutos
     * @param  array<string, mixed>  $dre
     * @param  array<string, mixed>  $dreContabilPoc
     * @return array{status: string, differences: array<string, float>, warnings: list<string>}
     */
    /**
     * @param  array<string, float>  $totais
     * @param  array<string, array<string, mixed>>  $fluxo
     * @param  array<string, mixed>  $dadosProdutos
     * @param  array<string, mixed>  $dre
     * @param  array<string, mixed>  $dreContabilPoc
     * @param  array<string, mixed>  $indicadoresFinanceiros
     * @return array{status: string, differences: array<string, float>, warnings: list<string>, checks: array<string, mixed>}
     */
    private function buildReconciliation(
        array $totais,
        array $fluxo,
        array $dadosProdutos,
        array $dre,
        array $dreContabilPoc,
        array $indicadoresFinanceiros = [],
    ): array {
        $receitaFluxo = (float) ($totais['receita'] ?? 0.0);
        $despesaFluxo = (float) (($totais['custo_direto'] ?? 0.0)
            + ($totais['impostos'] ?? 0.0)
            + ($totais['custos_operacionais'] ?? 0.0)
            + ($totais['custos_financeiros'] ?? 0.0));
        $saldoFinal = $receitaFluxo - $despesaFluxo;
        $lucroTotais = (float) ($totais['lucro'] ?? 0.0);

        $unidadesTotais = (float) ($dadosProdutos['totalUnidades'] ?? 0.0);
        $permutas = (float) ($dadosProdutos['permutas'] ?? 0.0);
        $unidadesComercializaveis = (float) ($dadosProdutos['totalUnidadesConstrutora'] ?? max(0.0, $unidadesTotais - $permutas));
        $vendasAcumuladas = 0.0;
        foreach ($fluxo as $linha) {
            $vendasAcumuladas += (float) ($linha['unidades_vendidas'] ?? 0);
        }
        $estoqueFinal = max(0.0, $unidadesComercializaveis - $vendasAcumuladas);

        $saldoDividaFinal = (float) (data_get($indicadoresFinanceiros, 'divida_pj.saldo_final') ?? 0.0);
        $curvaObra = $dadosProdutos['curvaObraAgregada'] ?? [];
        $somaCurva = is_array($curvaObra) ? array_sum($curvaObra) : 0.0;

        $differences = [
            'saldo_vs_lucro_totais' => round($saldoFinal - $lucroTotais, 2),
            'unidades_identidade' => round(($unidadesComercializaveis + $permutas) - $unidadesTotais, 4),
            'unidades_vendidas_estoque_permutas' => round(($vendasAcumuladas + $estoqueFinal + $permutas) - $unidadesTotais, 4),
            'saldo_divida_final' => round($saldoDividaFinal, 2),
            'curva_obra_vs_100' => round($somaCurva - 100.0, 4),
        ];

        $warnings = [];
        $failed = false;

        foreach ($differences as $key => $diff) {
            $tolerance = match (true) {
                str_contains($key, 'unidades') => 0.0001,
                str_contains($key, 'curva') => 0.01,
                default => 1.0,
            };
            if (abs($diff) > $tolerance) {
                $failed = true;
                $warnings[] = "Invariante {$key} divergiu por {$diff}.";
            }
        }

        if (! empty($dreContabilPoc['estouro_orcamento'])) {
            $warnings[] = (string) ($dreContabilPoc['warning'] ?? 'Estouro de orçamento POC.');
        }

        if (! is_finite($receitaFluxo) || ! is_finite($despesaFluxo)) {
            $failed = true;
            $warnings[] = 'Totais de fluxo contêm valores não finitos.';
        }

        return [
            'status' => $failed ? 'failed' : 'ok',
            'differences' => $differences,
            'warnings' => $warnings,
            'checks' => [
                'receita_caixa_total' => round($receitaFluxo, 2),
                'despesa_caixa_total' => round($despesaFluxo, 2),
                'saldo_final' => round($saldoFinal, 2),
                'unidades_totais' => $unidadesTotais,
                'unidades_permuta' => $permutas,
                'unidades_comercializaveis' => $unidadesComercializaveis,
                'unidades_vendidas' => round($vendasAcumuladas, 2),
                'estoque_final' => round($estoqueFinal, 2),
                'poc_receita' => $dreContabilPoc['receita_reconhecida_poc'] ?? null,
                'saldo_divida_final' => $saldoDividaFinal,
                'curva_obra_soma' => round($somaCurva, 4),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{
     *   inicioIncorporacao: Carbon,
     *   dataLancamento: Carbon,
     *   fimLancamento: Carbon,
     *   inicioObra: Carbon,
     *   fimObra: Carbon,
     *   dataEntrega: Carbon,
     *   inicioPos: Carbon,
     *   fimPos: Carbon
     * }
     */
    private function calcularPeriodos(Carbon $dataInicio, array $params): array
    {
        $dataLancamento = $dataInicio->copy()->startOfMonth();
        $inicioIncorporacao = $dataLancamento->copy()->subMonths(max(1, (int) ($params['mesesIncorporacao'] ?? 1)));
        $fimLancamento = $dataLancamento->copy()->addMonths(max(1, (int) ($params['mesesLancamento'] ?? 1)) - 1);
        $inicioObra = $fimLancamento->copy()->addMonth();
        $fimObra = $inicioObra->copy()->addMonths(max(1, (int) ($params['mesesObra'] ?? 1)) - 1);
        // meses_entrega: meses entre fim da obra e a data de entrega (mínimo 1).
        $mesesEntrega = max(1, (int) ($params['mesesEntrega'] ?? 1));
        $dataEntrega = $fimObra->copy()->addMonths($mesesEntrega);
        $inicioPos = $dataEntrega->copy();
        $fimPos = $inicioPos->copy()->addMonths(max(1, (int) ($params['mesesPosObra'] ?? 1)) - 1);

        return compact('inicioIncorporacao', 'dataLancamento', 'fimLancamento', 'inicioObra', 'fimObra', 'dataEntrega', 'inicioPos', 'fimPos');
    }

    private function identificarPeriodo(Carbon $data, array $datas): string
    {
        $mesAtual = $this->mesAno($data);
        $mesLancamento = $this->mesAno($datas['dataLancamento']);
        $mesFimLancamento = $this->mesAno($datas['fimLancamento']);
        $mesInicioObra = $this->mesAno($datas['inicioObra']);
        $mesFimObra = $this->mesAno($datas['fimObra']);
        $mesEntrega = $this->mesAno($datas['dataEntrega']);
        $mesInicioPos = $this->mesAno($datas['inicioPos']);

        if ($mesAtual < $mesLancamento) {
            return 'Incorporação';
        }
        if ($mesAtual >= $mesLancamento && $mesAtual <= $mesFimLancamento) {
            return 'Lançamento';
        }
        if ($mesAtual >= $mesInicioObra && $mesAtual <= $mesFimObra) {
            return 'Obra';
        }
        if ($mesAtual === $mesEntrega) {
            return 'Entrega';
        }
        if ($mesAtual >= $mesInicioPos) {
            return 'Pós-Obra';
        }

        return 'Transição';
    }

    private function mesAno(Carbon $data): string
    {
        return $data->copy()->startOfMonth()->format('Y-m');
    }

    /**
     * @param  list<array<string, mixed>>  $produtos
     * @param  array<string, Carbon>  $datas
     * @param  array<string, mixed>  $params
     */
    private function preCalcularRecebiveis(array $produtos, array $datas, array $params, ViabilidadeFluxoContext $ctx): void
    {
        if (! $ctx->perfil->isCef()) {
            $this->preCalcularVendas($produtos, $datas, $ctx);
        }

        if ($ctx->perfil->isApoioProducao()) {
            $this->preCalcularRecebiveisCef($produtos, $datas, $params, $ctx);
        } elseif ($ctx->perfil->isProprio()) {
            $this->preCalcularRecebiveisProprio($produtos, $datas, $params, $ctx);
        } elseif ($ctx->perfil->isPlanoEmpresario()) {
            $this->preCalcularRecebiveisPlanoEmpresario($produtos, $datas, $params, $ctx);
        } else {
            $this->preCalcularRecebiveisAlocacaoRecursos($produtos, $datas, $ctx);
        }

        $this->aplicarInadimplencia($ctx, $params);
    }

    /**
     * A curva comercial determina o mês da venda independentemente do modelo
     * de recebimento. No Apoio à Produção o cache continua sendo inicializado
     * pelo caminho legado para preservar exatamente o cálculo existente.
     *
     * @param  list<array<string, mixed>>  $produtos
     * @param  array<string, Carbon>  $datas
     */
    private function preCalcularVendas(array $produtos, array $datas, ViabilidadeFluxoContext $ctx): void
    {
        $ctx->vendasPorMes = [];
        $dataLancamento = $datas['dataLancamento']->copy()->startOfMonth();

        foreach ($produtos as $produto) {
            $curvaVendas = $this->curvaService->normalizarCurva(
                $this->curvaService->extrairCurva($produto['curva_vendas'] ?? null)
            );
            $unidades = max(
                0.0,
                (float) ($produto['quantidade_unidades'] ?? 0.0) - (float) ($produto['permutas'] ?? 0.0)
            );

            foreach ($curvaVendas as $mesVenda => $percentualVenda) {
                if ($percentualVenda <= 0.0) {
                    continue;
                }

                $mes = $dataLancamento->copy()->addMonths($mesVenda)->format('Y-m');
                $ctx->vendasPorMes[$mes] = ($ctx->vendasPorMes[$mes] ?? 0.0)
                    + ($unidades * $percentualVenda / 100);
            }
        }

        ksort($ctx->vendasPorMes);
    }

    /**
     * @param  list<array<string, mixed>>  $produtos
     * @param  array<string, Carbon>  $datas
     * @param  array<string, mixed>  $params
     */
    private function preCalcularRecebiveisCef(array $produtos, array $datas, array $params, ViabilidadeFluxoContext $ctx): void
    {
        $ctx->recursosProprios = [];

        $dataLancamento = $datas['dataLancamento'];
        $dataEntrega = $datas['dataEntrega'];
        $prazoLancamento = max(1, (int) ($params['mesesLancamento'] ?? 1));
        $prazoObra = max(1, (int) ($params['mesesObra'] ?? 1));
        $prazoTotalObra = $prazoLancamento + $prazoObra;
        $prazoPosChave = 36;

        foreach ($produtos as $produto) {
            $curvaVendas = $this->curvaService->extrairCurva($produto['curva_vendas'] ?? null);
            $curvaVendas = $this->curvaService->normalizarCurva($curvaVendas);

            $unidadesProduto = $produto['quantidade_unidades'] ?? 1;
            $permutasProduto = $produto['permutas'] ?? 0;
            $unidadesConstrutora = max(1, $unidadesProduto - $permutasProduto);
            $precoProduto = $produto['preco'] ?? 0;
            $fin = $produto['financeiro'];

            $percentualSinal = $this->normalizarPercentual($fin['sinal'] ?? null, 0.02);
            $percentualObra = $this->normalizarPercentual($fin['parcela_obra'] ?? null, 0.09);
            $percentualPos = $this->normalizarPercentual($fin['parcela_posChave'] ?? null, 0.09);
            $qtdParcelasPos = max(1, (int) ($fin['qtde_parcelas_posChave'] ?? $prazoPosChave));

            $taxaCorrecaoObraAnual = $this->normalizarPercentual($fin['correcao_anualObra'] ?? null, 0.0, true);
            $taxaCorrecaoPosAnual = $this->normalizarPercentual($fin['correcao_anualPosChave'] ?? null, 0.045, true);
            // variavel_correcao: taxa anual global adicional (premissa/viabilidade).
            $variavelCorrecao = max(0.0, (float) ($params['variavelCorrecao'] ?? 0.0));
            $taxaCorrecaoObraAnual += $variavelCorrecao;
            $taxaCorrecaoPosAnual += $variavelCorrecao;
            $jurosMensalPos = $this->normalizarPercentual($fin['juros_mensalPosChave'] ?? null, 0.01, true);

            $r_obra = $taxaCorrecaoObraAnual > 0
                ? pow(1 + $taxaCorrecaoObraAnual, 1 / 12.0) - 1
                : 0.0;
            $r_pos = pow(1 + $taxaCorrecaoPosAnual, 1 / 12.0) - 1;
            $valorObraTotal = $precoProduto * $percentualObra * $unidadesConstrutora;
            $obraVendidaAcumulada = 0.0;

            foreach ($curvaVendas as $mesVenda => $percentualVenda) {
                if ($percentualVenda <= 0) {
                    continue;
                }

                $s = $mesVenda + 1;
                $unidadesVendidas = $unidadesConstrutora * $percentualVenda / 100;
                $valorSinal = $precoProduto * $percentualSinal;
                $valorObra = $precoProduto * $percentualObra;
                $valorObraCoorte = $valorObra * $unidadesVendidas;

                $dataRecebimento = $dataLancamento->copy()->addMonths($s - 1);
                $chaveMes = $dataRecebimento->format('Y-m');

                $ctx->recursosProprios[$chaveMes]['sinal'] =
                    ($ctx->recursosProprios[$chaveMes]['sinal'] ?? 0) + ($valorSinal * $unidadesVendidas);

                $obraVendidaAcumulada += $valorObraCoorte;
                $saldoRemanescenteObra = max(0.0, $valorObraTotal - $obraVendidaAcumulada);

                if ($saldoRemanescenteObra > 0.0 && $r_obra > 0.0) {
                    $ctx->recursosProprios[$chaveMes]['correcao_obra'] =
                        ($ctx->recursosProprios[$chaveMes]['correcao_obra'] ?? 0.0)
                        + ($saldoRemanescenteObra * $r_obra);
                }

                $numObraParcelas = max(1, $prazoTotalObra - ($s - 1));

                if ($valorObra > 0) {
                    $parcelaObraNominal = $valorObra / $numObraParcelas;

                    for ($i = 0; $i < $numObraParcelas; $i++) {
                        $mesRecebimento = $s + $i;
                        $dataRecebimento = $dataLancamento->copy()->addMonths($mesRecebimento - 1);
                        $chaveMes = $dataRecebimento->format('Y-m');

                        $valorParcelaMes = $parcelaObraNominal * $unidadesVendidas;

                        $ctx->recursosProprios[$chaveMes]['parcelas_obra'] =
                            ($ctx->recursosProprios[$chaveMes]['parcelas_obra'] ?? 0) + $valorParcelaMes;
                    }
                }
            }

            $valorPosTotal = $precoProduto * $percentualPos * $unidadesConstrutora;
            $amortizacao = $valorPosTotal / $qtdParcelasPos;

            for ($k = 1; $k <= $qtdParcelasPos; $k++) {
                $saldoDevedor = $valorPosTotal - ($amortizacao * $k);
                $jurosMes = $saldoDevedor * $jurosMensalPos;
                $correcaoMes = $saldoDevedor * $r_pos;

                $dataRecebimento = $dataEntrega->copy()->addMonths($k - 1);
                $chaveMes = $dataRecebimento->format('Y-m');

                $ctx->recursosProprios[$chaveMes]['parcelas_pos'] =
                    ($ctx->recursosProprios[$chaveMes]['parcelas_pos'] ?? 0) + $amortizacao;
                $ctx->recursosProprios[$chaveMes]['juros'] =
                    ($ctx->recursosProprios[$chaveMes]['juros'] ?? 0) + $jurosMes;
                $ctx->recursosProprios[$chaveMes]['correcao'] =
                    ($ctx->recursosProprios[$chaveMes]['correcao'] ?? 0) + $correcaoMes;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $produtos
     * @param  array<string, Carbon>  $datas
     * @param  array<string, mixed>  $params
     */
    private function preCalcularRecebiveisProprio(array $produtos, array $datas, array $params, ViabilidadeFluxoContext $ctx): void
    {
        $ctx->recursosProprios = [];

        $dataLancamento = $datas['dataLancamento'];
        $dataEntrega = $datas['dataEntrega'];
        $prazoLancamento = $params['mesesLancamento'];
        $prazoObra = $params['mesesObra'];
        $endObra = $prazoObra;

        foreach ($produtos as $produto) {
            $curvaVendas = $this->curvaService->extrairCurva($produto['curva_vendas'] ?? null);
            $curvaVendas = $this->curvaService->normalizarCurva($curvaVendas);

            $unidadesProduto = $produto['quantidade_unidades'] ?? 1;
            $permutasProduto = $produto['permutas'] ?? 0;
            $unidadesConstrutora = max(1, $unidadesProduto - $permutasProduto);
            $precoProduto = $produto['preco'] ?? 0;
            $fin = $produto['financeiro'];
            $percentualSinal = $this->normalizarPercentual($fin['sinal'] ?? null, 0.02);
            $baloesAnuais = $produto['baloes_anuais'] ?? [];
            $balaoEntregaModo = $produto['balao_entrega_modo'] ?? 'saldo_restante';

            foreach ($curvaVendas as $mesVenda => $percentualVenda) {
                if ($percentualVenda <= 0) {
                    continue;
                }

                $s = $mesVenda + 1;
                $unidadesVendidas = $unidadesConstrutora * $percentualVenda / 100;
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
                    $inicioObraCoorte = max($s, 1);
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
     * Plano Empresário: preserva sinal/parcelas pagos diretamente pelos
     * clientes e concentra o saldo financiado pelo comprador no repasse PF da
     * entrega. O financiamento PJ da obra é tratado no fluxo financeiro.
     *
     * @param  list<array<string, mixed>>  $produtos
     * @param  array<string, Carbon>  $datas
     * @param  array<string, mixed>  $params
     */
    private function preCalcularRecebiveisPlanoEmpresario(
        array $produtos,
        array $datas,
        array $params,
        ViabilidadeFluxoContext $ctx,
    ): void {
        $this->preCalcularRecebiveisCef($produtos, $datas, $params, $ctx);

        $dataLancamento = $datas['dataLancamento']->copy()->startOfMonth();
        $dataEntrega = $datas['dataEntrega']->copy()->startOfMonth();

        foreach ($produtos as $produto) {
            $curvaVendas = $this->curvaService->normalizarCurva(
                $this->curvaService->extrairCurva($produto['curva_vendas'] ?? null)
            );
            $unidades = max(
                0.0,
                (float) ($produto['quantidade_unidades'] ?? 0.0) - (float) ($produto['permutas'] ?? 0.0)
            );
            $precoBruto = max(0.0, (float) ($produto['preco'] ?? 0.0));
            $precoLiquido = max(0.0, $precoBruto - (float) ($produto['pgto_por_lote'] ?? 0.0));
            $financeiro = is_array($produto['financeiro'] ?? null) ? $produto['financeiro'] : [];
            $percentualCliente = min(1.0,
                $this->normalizarPercentual($financeiro['sinal'] ?? null, 0.02)
                + $this->normalizarPercentual($financeiro['parcela_obra'] ?? null, 0.09)
                + $this->normalizarPercentual($financeiro['parcela_posChave'] ?? null, 0.09)
            );
            $saldoRepasseUnitario = max(0.0, $precoLiquido - ($precoBruto * $percentualCliente));

            foreach ($curvaVendas as $mesVenda => $percentualVenda) {
                if ($percentualVenda <= 0.0 || $saldoRepasseUnitario <= 0.0) {
                    continue;
                }

                $dataVenda = $dataLancamento->copy()->addMonths($mesVenda)->startOfMonth();
                $dataRepasse = $dataVenda->greaterThan($dataEntrega) ? $dataVenda : $dataEntrega;
                $mesRepasse = $dataRepasse->format('Y-m');
                $unidadesVendidas = $unidades * $percentualVenda / 100;
                $ctx->recursosProprios[$mesRepasse]['repasse_pf'] =
                    ($ctx->recursosProprios[$mesRepasse]['repasse_pf'] ?? 0.0)
                    + ($saldoRepasseUnitario * $unidadesVendidas);
            }
        }

        ksort($ctx->recursosProprios);
    }

    /**
     * Alocação de Recursos: não há entrada bancária durante a obra. As vendas
     * formalizadas antes da conclusão são liberadas juntas na entrega; vendas
     * posteriores entram no respectivo mês da curva comercial.
     *
     * @param  list<array<string, mixed>>  $produtos
     * @param  array<string, Carbon>  $datas
     */
    private function preCalcularRecebiveisAlocacaoRecursos(
        array $produtos,
        array $datas,
        ViabilidadeFluxoContext $ctx,
    ): void {
        $ctx->recursosProprios = [];
        $dataLancamento = $datas['dataLancamento']->copy()->startOfMonth();
        $dataEntrega = $datas['dataEntrega']->copy()->startOfMonth();

        foreach ($produtos as $produto) {
            $curvaVendas = $this->curvaService->normalizarCurva(
                $this->curvaService->extrairCurva($produto['curva_vendas'] ?? null)
            );
            $unidades = max(
                0.0,
                (float) ($produto['quantidade_unidades'] ?? 0.0) - (float) ($produto['permutas'] ?? 0.0)
            );
            $valorUnitario = max(0.0, (float) ($produto['preco'] ?? 0.0) - (float) ($produto['pgto_por_lote'] ?? 0.0));

            foreach ($curvaVendas as $mesVenda => $percentualVenda) {
                if ($percentualVenda <= 0.0 || $valorUnitario <= 0.0) {
                    continue;
                }

                $dataVenda = $dataLancamento->copy()->addMonths($mesVenda)->startOfMonth();
                $dataRepasse = $dataVenda->greaterThan($dataEntrega) ? $dataVenda : $dataEntrega;
                $mesRepasse = $dataRepasse->format('Y-m');
                $unidadesVendidas = $unidades * $percentualVenda / 100;
                $ctx->recursosProprios[$mesRepasse]['repasse_pf'] =
                    ($ctx->recursosProprios[$mesRepasse]['repasse_pf'] ?? 0.0)
                    + ($valorUnitario * $unidadesVendidas);
            }
        }

        ksort($ctx->recursosProprios);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function aplicarInadimplencia(ViabilidadeFluxoContext $ctx, array $params): void
    {
        if (! $ctx->perfil->isProprio()) {
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

            $dataAtual = Carbon::parse($chaveMes.'-01');
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
     * @param  array<string, mixed>  $dadosProdutos
     * @param  array<string, Carbon>  $datas
     */
    private function inicializarCachesCef(array $dadosProdutos, array $datas, ViabilidadeFluxoContext $ctx): void
    {
        $ctx->vendasPorMes = [];
        $ctx->vendasAcumuladas = 0.0;
        $ctx->demandaAtingida = false;
        $ctx->mesDemandaAtingida = null;
        $ctx->txContratacaoPaga = false;
        $ctx->medicaoObraAcumulada = 0.0;
        $ctx->curvaObraAcumulada = 0.0;
        $ctx->mesObraAtual = 0;
        $ctx->valorMedicaoTotal = 0.0;

        // Demanda mínima CEF ponderada por produto:
        // sum(unidades_comercializáveis_produto × demanda_produto).
        // Dois produtos a 30% => 30% do total comercializável, não 60%.
        $ctx->demandaMinima = 0.0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            $unidadesProduto = max(
                0,
                (int) ($produto['quantidade_unidades'] ?? 0) - (int) ($produto['permutas'] ?? 0)
            );
            $demandaPct = $this->normalizarPercentual($produto['demanda_minCef'] ?? null);
            $ctx->demandaMinima += $unidadesProduto * $demandaPct;
        }

        $this->receitasCalculator->inicializarValorMedicaoTotal($dadosProdutos, $datas, $ctx);
    }

    private function normalizarPercentual(
        mixed $valor,
        float $fallback = 0.0,
        bool $umComoPercentual = false
    ): float {
        if ($valor === null || $valor === '') {
            return $fallback;
        }

        $percentual = (float) $valor;
        if ($percentual <= 0.0) {
            return 0.0;
        }

        if ($percentual < 1.0 || ($percentual === 1.0 && ! $umComoPercentual)) {
            return $percentual;
        }

        return $percentual / 100;
    }

    /**
     * @return list<float>
     */
    private function agregarCurvaObra(int $mesesObra): array
    {
        return $this->curvaService->getCurvaObraParaPrazo($mesesObra);
    }
}
