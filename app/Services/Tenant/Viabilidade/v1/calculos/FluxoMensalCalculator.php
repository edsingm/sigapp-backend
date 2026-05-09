<?php

namespace App\Services\Tenant\Viabilidade\v1\Calculos;

use App\Models\Tenant\Terreno;
use App\Services\Tenant\Viabilidade\v1\CurvaService;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeFluxoContext;
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

        $dadosProdutos['curvaObraAgregada'] = $this->agregarCurvaObra($params['mesesObra']);
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

        foreach ($periodoReceitas as $dataReceita) {
            $mesReceita = $dataReceita->format('Y-m');
            $receitasMes = $this->receitasCalculator->calcular($mesReceita, $dadosProdutos, $datas, $params, $ctxReceitas);
            $totalJurosCorrecoesPrevistos += (float) ($receitasMes['juros_correcao'] ?? 0.0);

            if (($receitasMes['total'] ?? 0.0) > 0.01) {
                $mesesComReceitas++;
            }
        }

        $dadosProdutos['correcaoSobreVgv'] = $totalJurosCorrecoesPrevistos;
        $dadosProdutos['vgvComCorrecao'] = ($dadosProdutos['vgvSemValorTerrenista'] ?? 0) + $totalJurosCorrecoesPrevistos;
        $ctx->parceriaVgvTotal = max(0.0, ($params['parceriaVgv'] ?? 0.0) * ($dadosProdutos['vgvComCorrecao'] ?? 0.0));

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

        foreach ($periodo as $data) {
            $mes = $data->format('Y-m');

            $receitas = $this->receitasCalculator->calcular($mes, $dadosProdutos, $datas, $params, $ctx);
            $despesas = $this->despesasCalculator->calcular($mes, $receitas, $dadosProdutos, $datas, $params, $ctx);

            $lucroMes = $receitas['total'] - $despesas['total'];
            $saldoAcumulado += $lucroMes;

            $receitaRpMes = $receitas['detalhes']['Recursos Próprios'] ?? 0.0;
            $lucroSemCefMes = $receitaRpMes - $despesas['total'];
            $vendasMesBrutas = max(0.0, (float) ($ctx->vendasPorMes[$mes] ?? 0.0));
            $vendasMesComCarry = $vendasMesBrutas + $fracaoVendasCarregada;
            $unidadesVendidasMes = max(0.0, floor($vendasMesComCarry + 1e-9));
            $estoqueRemanescenteMes = max(0.0, $totalUnidadesComercializaveis - $unidadesVendidasAcumuladasFluxo);
            $unidadesVendidasMes = min($unidadesVendidasMes, $estoqueRemanescenteMes);
            $fracaoVendasCarregada = max(0.0, $vendasMesComCarry - $unidadesVendidasMes);
            $unidadesVendidasAcumuladasFluxo += $unidadesVendidasMes;

            $fluxo[$mes] = [
                'periodo' => $this->identificarPeriodo($data, $datas),
                'receitas' => $receitas['detalhes'],
                'despesas' => $despesas['detalhes'],
                'saldo_mes' => round($lucroMes, 2),
                'saldo_acumulado_mes' => round($saldoAcumulado, 2),
                'unidades_vendidas' => $unidadesVendidasMes,
            ];

            $fluxoTir[] = ['data' => $data->copy(), 'valor' => $lucroMes];
            $fluxoTirSemCef[] = ['data' => $data->copy(), 'valor' => $lucroSemCefMes];

            $totais['receita'] += $receitas['total'];
            $totais['custo_direto'] += $despesas['categorias']['custo_direto'];
            $totais['impostos'] += $despesas['categorias']['impostos'];
            $totais['custos_operacionais'] += $despesas['categorias']['custos_operacionais'];
            $totais['custos_financeiros'] += $despesas['categorias']['custos_financeiros'];
            $totais['lucro'] += $lucroMes;

            $totalJurosCorrecoes += $receitas['juros_correcao'] ?? 0.0;
        }

        $dadosProdutos['correcaoSobreVgv'] = $totalJurosCorrecoes;
        $dadosProdutos['vgvComCorrecao'] = ($dadosProdutos['vgvSemValorTerrenista'] ?? 0) + $totalJurosCorrecoes;

        [$fluxoFinanceiro, $indicadoresFinanceiros] = $this->indicadoresCalculator->calcularIndicadoresFinanceiros($fluxo, $datas, $params, $dadosProdutos);
        $indicadoresVso = $this->indicadoresCalculator->calcularIndicadoresVso($fluxo, $dadosProdutos);
        $indicadoresVsoJanelas = $this->indicadoresCalculator->calcularIndicadoresVsoJanelas($fluxo, $dadosProdutos);

        $indicadores = [
            'tir_operacional' => $this->indicadoresCalculator->calcularTir($fluxoTir),
            'tir_sem_cef' => $this->indicadoresCalculator->calcularTir($fluxoTirSemCef),
            'exposicao_maxima_operacional' => collect($fluxo)->min('saldo_acumulado_mes'),
            'margem_liquida' => $totais['receita'] > 0 ? ($totais['lucro'] / $totais['receita']) : 0.0,
        ];

        $dre = $this->dreCalculator->calcular($fluxo, $dadosProdutos, $params);
        $dreContabilPoc = $this->pocCalculator->calcularDreContabilPoc($fluxo, $dre, $dadosProdutos);
        $dreContabilPocMensal = $this->pocCalculator->calcularQuadroPocMensal($fluxo, $dre, $dadosProdutos);
        $dreContabilPocMensalBlocos = $this->pocCalculator->calcularQuadroPocMensalPorBlocos($fluxo, $dre, $dadosProdutos);
        $dreCaixa = $this->pocCalculator->calcularDreCaixa($totais);
        $ponteReconcilicao = $this->pocCalculator->calcularPonteReconcilicao($dreCaixa, $dre, $dreContabilPocMensalBlocos);

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
    }

    private function calcularPeriodos(Carbon $dataInicio, array $params): array
    {
        $dataLancamento = $dataInicio->copy();
        $inicioIncorporacao = $dataLancamento->copy()->subMonths($params['mesesIncorporacao']);
        $fimLancamento = $dataLancamento->copy()->addMonths($params['mesesLancamento'] - 1);
        $inicioObra = $fimLancamento->copy()->addMonth();
        $fimObra = $inicioObra->copy()->addMonths($params['mesesObra'] - 1);
        $dataEntrega = $fimObra->copy()->addMonth();
        $inicioPos = $dataEntrega->copy();
        $fimPos = $inicioPos->copy()->addMonths($params['mesesPosObra'] - 1);

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

    private function preCalcularRecebiveis(array $produtos, array $datas, array $params, ViabilidadeFluxoContext $ctx): void
    {
        if ($ctx->perfil->isCef()) {
            $this->preCalcularRecebiveisCef($produtos, $datas, $params, $ctx);
        } else {
            $this->preCalcularRecebiveisProprio($produtos, $datas, $params, $ctx);
        }

        $this->aplicarInadimplencia($ctx, $params);
    }

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

            $percentualSinal = ($fin['sinal'] ?? 2) / 100;
            $percentualObra = ($fin['parcela_obra'] ?? 9) / 100;
            $percentualPos = ($fin['parcela_posChave'] ?? 9) / 100;
            $qtdParcelasPos = max(1, (int) ($fin['qtde_parcelas_posChave'] ?? $prazoPosChave));

            $taxaCorrecaoObraAnual = ((float) ($fin['correcao_anualObra'] ?? 0)) / 100;
            $taxaCorrecaoPosAnual = ((float) ($fin['correcao_anualPosChave'] ?? 4.5)) / 100;
            $jurosMensalPos = ((float) ($fin['juros_mensalPosChave'] ?? 1)) / 100;

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
            $percentualSinal = ($fin['sinal'] ?? 2) / 100;
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

        $unidadesTotais = max(1, $dadosProdutos['totalUnidadesConstrutora'] ?? $dadosProdutos['totalUnidades'] ?? 1);
        $ctx->demandaMinima = 0.0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            $demandaPct = ($produto['demanda_minCef'] ?? 0) / 100;
            $ctx->demandaMinima += $unidadesTotais * $demandaPct;
        }

        $this->receitasCalculator->inicializarValorMedicaoTotal($dadosProdutos, $datas, $ctx);
    }

    private function agregarCurvaObra(int $mesesObra): array
    {
        return $this->curvaService->getCurvaObraParaPrazo($mesesObra);
    }
}
