<?php

namespace App\Services\Tenant\Viabilidade\v1\Calculos;

use App\Services\Tenant\Viabilidade\v1\CurvaService;
use App\Services\Tenant\Viabilidade\v1\ImpostosService;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeFluxoContext;
use Carbon\Carbon;

class DespesasCalculator
{
    public function __construct(
        private readonly CurvaService $curvaService,
        private readonly ImpostosService $impostosService,
        private readonly DreCalculator $dreCalculator,
    ) {}

    public function calcular(
        string $mes,
        array $receitas,
        array $dadosProdutos,
        array $datas,
        array $params,
        ?ViabilidadeFluxoContext $ctx = null
    ): array {
        $ctx ??= new ViabilidadeFluxoContext;
        $dataAtual = Carbon::parse($mes.'-01');
        $periodo = $this->identificarPeriodo($dataAtual, $datas);
        $vgv = $dadosProdutos['vgv'];
        $custoObra = $this->custoObraTotal($dadosProdutos);

        $diretos = $this->calcularCustosDiretos($mes, $periodo, $datas, $params, $vgv, $custoObra, $dadosProdutos);

        $tributos = $this->impostosService->calcularTributosPorProduto(
            $receitas['total'],
            $receitas['juros_correcao'],
            $dadosProdutos['produtos'],
            $vgv,
            $params
        );

        $operacionais = $this->calcularCustosOperacionais($mes, $dadosProdutos, $datas, $params, $ctx);

        $percProdutosCef = $ctx->perfil->isCef() ? $params['percentualProdutosCef'] : 0.0;
        $financeiros = $receitas['total'] * ($percProdutosCef + $params['percentualOutrasDespesasFinanceiras']);

        $custoTerreno = $this->calcularCustoTerreno($receitas['total'], $dadosProdutos, $params);
        $pagamentoParceriaTerreno = $this->calcularPagamentoParceriaTerreno($mes, $receitas['total'], $datas, $params, $ctx);

        $detalhesOperacionais = [];
        foreach ($operacionais['detalhes'] as $nome => $valor) {
            $detalhesOperacionais['Operacional - '.$nome] = round($valor, 2);
        }

        $unidadesVendidasMes = $ctx->vendasPorMes[$mes] ?? 0;
        $totalUnidadesComercializaveis = max(1, $dadosProdutos['totalUnidadesConstrutora'] ?? $dadosProdutos['totalUnidades'] ?? 1);
        $vgvSemPermutas = $dadosProdutos['vgvSemUnidPermutas'] ?? $vgv;
        $valorPorUnidade = $vgvSemPermutas / $totalUnidadesComercializaveis;

        if ($ctx->perfil->isCef() && $unidadesVendidasMes > 0) {
            $itbiMensal = $unidadesVendidasMes * $valorPorUnidade * ($params['custoItbiIptu'] ?? 0);
            $detalhesOperacionais['ITBI/IPTU'] = round($itbiMensal, 2);
        }

        if ($ctx->perfil->isCef() && $unidadesVendidasMes > 0) {
            $registroMensal = $unidadesVendidasMes * ($params['custoRegistro'] ?? 0);
            $detalhesOperacionais['Registro'] = round($registroMensal, 2);
        }

        if ($ctx->perfil->isCef() && $ctx->demandaAtingida && $unidadesVendidasMes > 0) {
            $produtosCefMensal = $unidadesVendidasMes * $valorPorUnidade * ($params['percentualProdutosCef'] ?? 0);
            $detalhesOperacionais['Produtos Caixa'] = round($produtosCefMensal, 2);
        }

        if ($ctx->perfil->isCef() && $periodo === 'Lançamento' && ! $ctx->txContratacaoPaga) {
            $detalhesOperacionais['Taxa Contratação'] = round($params['custoContratacaoCef'] ?? 0, 2);
            $ctx->txContratacaoPaga = true;
        }

        if ($ctx->perfil->isCef() && $ctx->demandaAtingida && $unidadesVendidasMes > 0) {
            $contratosCefMensal = $unidadesVendidasMes * ($params['custoContratosCef'] ?? 0);
            $detalhesOperacionais['Contratos Caixa'] = round($contratosCefMensal, 2);
        }

        if ($ctx->perfil->isCef() && $periodo === 'Obra') {
            $detalhesOperacionais['Taxa Medição'] = round($params['custoMedicaoCef'] ?? 0, 2);
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

    private function calcularCustosDiretos(
        string $mes,
        string $periodo,
        array $datas,
        array $params,
        float $vgv,
        float $custoObraTotal,
        array $dadosProdutos
    ): array {
        $custos = [];
        $dataAtual = Carbon::parse($mes.'-01');

        $custoIncorp = $vgv * $params['percentualIncorporacao'];
        $areaComumTotal = $params['custoAreaComum'] * ($dadosProdutos['totalUnidades'] ?? 0);
        $mesesIncorporacao = max(1, (int) $params['mesesIncorporacao']);
        $mesesPosLancamento = max(1, (int) $params['mesesObra']);

        if ($dataAtual->between($datas['inicioIncorporacao'], $datas['dataLancamento'])) {
            $custos['Incorporação Até Lançamento'] = round(($custoIncorp * $params['incorporacaoAteLancamento']) / $mesesIncorporacao, 2);
        }
        if ($dataAtual->between($datas['dataLancamento'], $datas['fimObra'])) {
            $custos['Incorporação Pós Lançamento'] = round(($custoIncorp * (1 - $params['incorporacaoAteLancamento'])) / $mesesPosLancamento, 2);
        }

        if ($periodo === 'Lançamento') {
            $custos['Obra (Lançamento)'] = round(($custoObraTotal * ($params['obraAteLancamento'] ?? 0.01)) / max(1, $params['mesesLancamento']), 2);
        }

        if ($periodo === 'Obra') {
            $mesObraIndex = (int) $datas['inicioObra']->diffInMonths($dataAtual) + 1;
            $curvaObra = $dadosProdutos['curvaObraAgregada'] ?? $this->agregarCurvaObra($params['mesesObra']);
            $percentualMes = $curvaObra[$mesObraIndex - 1] ?? 0.0;
            $custos['Obra'] = round($custoObraTotal * ($percentualMes / 100), 2);
            $custos['Canteiro'] = round($params['canteiroMensal'], 2);
            $custos['Área Comum'] = round($areaComumTotal / max(1, $params['mesesObra']), 2);
            $custos['M.O. Administrativa'] = round($params['moAdministrativa'], 2);
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

    private function calcularCustosOperacionais(
        string $mes,
        array $dadosProdutos,
        array $datas,
        array $params,
        ViabilidadeFluxoContext $ctx
    ): array {
        $despesasComerciais = $this->calcularDespesasComerciaisMensais($mes, $dadosProdutos, $datas, $params, $ctx);
        $marketing = $this->calcularMarketingMensal($mes, $dadosProdutos, $datas, $params, $ctx);

        return [
            'total' => $despesasComerciais['total'] + $marketing['total'],
            'detalhes' => array_merge($despesasComerciais['detalhes'], ['Marketing' => $marketing['total']]),
        ];
    }

    private function calcularCustoTerreno(float $receitaMes, array $dadosProdutos, array $params): float
    {
        $totalCustoTerreno = (float) ($params['compraTerreno'] ?? 0);
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

        $dataAtual = Carbon::parse($mes.'-01');
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

    private function calcularDespesasComerciaisMensais(
        string $mes,
        array $dadosProdutos,
        array $datas,
        array $params,
        ViabilidadeFluxoContext $ctx
    ): array {
        $dataAtual = Carbon::parse($mes.'-01');
        $vgvSemPermuta = $dadosProdutos['vgvSemUnidPermutas'] ?? 0;
        $unidadesConstrutora = max(1, $dadosProdutos['totalUnidadesConstrutora'] ?? $dadosProdutos['totalUnidades'] ?? 1);
        $ticketMedio = $vgvSemPermuta / $unidadesConstrutora;
        $unidadesVendidasMes = $ctx->vendasPorMes[$mes] ?? 0;
        $valorVendidoMes = $unidadesVendidasMes * $ticketMedio;
        $taxaComissaoMedia = (($params['percentualVendasHouse'] ?? 0) * ($params['comissaoHousePercentual'] ?? 0)) +
            ((1 - ($params['percentualVendasHouse'] ?? 0)) * ($params['comissaoImobiliariasPercentual'] ?? 0));
        $comissaoBaseMes = $valorVendidoMes * $taxaComissaoMedia;
        $comissaoVenda = $comissaoBaseMes * ($params['pagamentoComissaoVenda'] ?? 0);
        $comissaoDesligamento = $this->calcularComissaoDesligamentoMensal($mes, $ticketMedio, $params, $ctx);
        $bonusCca = ($params['bonusCca'] ?? 0) * $unidadesVendidasMes;
        $bonusGerente = $valorVendidoMes * ($params['bonusGerente'] ?? 0);
        $bonusGerenteRegional = $valorVendidoMes * ($params['bonusGerenteRegional'] ?? 0);
        $bonusCredito = $valorVendidoMes * ($params['bonusCredito'] ?? 0);
        $bonusGestorComercial = $valorVendidoMes * ($params['bonusGestorComercial'] ?? 0);
        $inicioAntesLancamento = $datas['dataLancamento']->copy()->subMonths(max(0, $params['marketingInicioAntesLancamento']));
        $standParcelado = $dataAtual->between($datas['dataLancamento'], $datas['fimLancamento']) ? (($params['standVendas'] ?? 0) / max(1, $params['mesesLancamento'])) : 0;
        $mobiliaParcelada = $dataAtual->between($inicioAntesLancamento, $datas['dataLancamento']->copy()->subMonth()) ? (($params['mobiliaDecoracao'] ?? 0) / max(1, $params['marketingInicioAntesLancamento'])) : 0;
        $gastosMensaisStand = $dataAtual->between($datas['dataLancamento'], $datas['fimObra']) ? ($vgvSemPermuta * ($params['gastosMensaisStand'] ?? 0)) : 0;
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

    private function calcularComissaoDesligamentoMensal(
        string $mes,
        float $ticketMedio,
        array $params,
        ViabilidadeFluxoContext $ctx
    ): float {
        $dataAtual = Carbon::parse($mes.'-01');
        $parcelamento = max(1, (int) ($params['parcelamentoComissaoMeses'] ?? 1));
        $taxaComissaoMedia = (($params['percentualVendasHouse'] ?? 0) * ($params['comissaoHousePercentual'] ?? 0)) +
            ((1 - ($params['percentualVendasHouse'] ?? 0)) * ($params['comissaoImobiliariasPercentual'] ?? 0));
        $percentualDesligamento = $params['pagamentoComissaoDesligamento'] ?? 0;
        $totalMes = 0.0;

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

    private function calcularMarketingMensal(
        string $mes,
        array $dadosProdutos,
        array $datas,
        array $params,
        ViabilidadeFluxoContext $ctx
    ): array {
        $dataAtual = Carbon::parse($mes.'-01');
        $baseMarketing = ($dadosProdutos['vgvSemUnidPermutas'] ?? 0) * ($params['percentualMarketing'] ?? 0);
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
            return 0.0;
        }
        $totalSeguros = $this->dreCalculator->calcularSegurosPorTipologia($dadosProdutos, $params);
        $mesesRateio = max(1, $params['mesesObra']);

        return $totalSeguros / $mesesRateio;
    }

    private function calcularAssistenciaTecnicaMensal(string $mes, array $datas, array $params, float $baseAssistencia): float
    {
        $dataAtual = Carbon::parse($mes.'-01');
        if (! $dataAtual->between($datas['inicioPos'], $datas['fimPos'])) {
            return 0.0;
        }

        $mesPosObra = (int) $datas['inicioPos']->diffInMonths($dataAtual) + 1;
        $curva = array_values($params['assistenciaTecnicaCurva'] ?? [50, 20, 10, 10, 10]);
        $indiceAno = min(count($curva) - 1, (int) floor(($mesPosObra - 1) / 12));
        $percentualAno = ($curva[$indiceAno] ?? 0) / 100;
        $totalAssistencia = $baseAssistencia * ($params['percentualAssistenciaTecnica'] ?? 0);

        return ($totalAssistencia * $percentualAno) / 12;
    }

    private function custoObraTotal(array $d): float
    {
        return ($d['custoObraHabitacao'] ?? 0.0) + ($d['custoInfraestrutura'] ?? 0.0) + ($d['custoNaoIncidente'] ?? 0.0);
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

        return 'Indefinido';
    }

    private function agregarCurvaObra(int $mesesObra): array
    {
        return $this->curvaService->getCurvaObraParaPrazo($mesesObra);
    }
}
