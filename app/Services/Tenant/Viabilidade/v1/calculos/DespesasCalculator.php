<?php

namespace App\Services\Tenant\Viabilidade\v1\Calculos;

use App\Services\Tenant\Viabilidade\v1\CurvaService;
use App\Services\Tenant\Viabilidade\v1\ViabilidadeFluxoContext;
use Carbon\Carbon;

class DespesasCalculator
{
    public function __construct(
        private readonly CurvaService $curvaService,
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

        $deducoes = $this->calcularDeducoesMensais(
            $receitas,
            $dadosProdutos,
            $params
        );

        $operacionais = $this->calcularCustosOperacionais($mes, $dadosProdutos, $datas, $params, $ctx);

        $percProdutosCef = $ctx->perfil->isCef() ? $params['percentualProdutosCef'] : 0.0;
        $financeiros = $receitas['total'] * ($percProdutosCef + $params['percentualOutrasDespesasFinanceiras']);

        $custoTerreno = $this->calcularCustoTerreno($receitas['total'], $dadosProdutos, $params);
        $pagamentoTerreno = $this->calcularPagamentoTerreno($mes, $periodo, $receitas, $dadosProdutos, $datas, $params, $ctx);

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

        $total = $diretos['total'] + $deducoes['total'] + $operacionais['total'] + $financeiros + $custoTerreno + $pagamentoTerreno['total'];

        return [
            'total' => $total,
            'detalhes' => array_merge($diretos['detalhes'], [
                'Deduções' => round($deducoes['total'], 2),
                'Deduções - RET/LP Imóveis' => round($deducoes['ret_imoveis'], 2),
                'Deduções - RET/LP Lotes' => round($deducoes['ret_lotes'], 2),
                'Deduções - ISS' => round($deducoes['iss'], 2),
                'Deduções - Outras' => round($deducoes['outras'], 2),
                'Operacional' => round($operacionais['total'], 2),
                'Financeiro' => round($financeiros, 2),
                'Custo Terreno' => round($custoTerreno, 2),
                'Pagamento Terreno' => round($pagamentoTerreno['total'], 2),
                'Pagamento Terreno - Parceria VGV' => round($pagamentoTerreno['parceria'], 2),
                'Pagamento Terreno - Permuta Física' => round($pagamentoTerreno['permuta_fisica'], 2),
                'Pagamento Terreno - Comissão Corretor' => round($pagamentoTerreno['comissao_corretor'], 2),
            ], $detalhesOperacionais),
            'categorias' => [
                'custo_direto' => $diretos['total'] + $custoTerreno + $pagamentoTerreno['total'],
                'impostos' => $deducoes['total'],
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
        $totalUnidades = (int) ($dadosProdutos['totalUnidadesConstrutora'] ?? $dadosProdutos['totalUnidades'] ?? 0);
        $areaComumTotal = ((float) ($params['custoAreaComum'] ?? 0.0)) * max(0, $totalUnidades);

        $custoIncorp = $vgv * $params['percentualIncorporacao'];
        $ri = $custoIncorp * $params['incorporacaoRi'];
        $entrega = $custoIncorp * $params['incorporacaoEntrega'];
        $restante = max(0.0, $custoIncorp - $ri - $entrega);
        $ateLancamento = $restante * $params['incorporacaoAteLancamento'];
        $posLancamento = max(0.0, $restante - $ateLancamento);
        $mesesIncorpMaisLanc = max(1, (int) $params['mesesIncorporacao'] + (int) $params['mesesLancamento']);
        $mesesLancMaisObra = max(1, (int) $params['mesesLancamento'] + (int) $params['mesesObra']);
        $ultimoMesIncorporacao = $datas['dataLancamento']->copy()->subMonth()->startOfMonth();

        if ($periodo === 'Incorporação' || $periodo === 'Lançamento') {
            $custos['Incorporação Até Lançamento'] = round($ateLancamento / $mesesIncorpMaisLanc, 2);
        }
        if ($periodo === 'Lançamento' || $periodo === 'Obra') {
            $custos['Incorporação Pós Lançamento'] = round($posLancamento / $mesesLancMaisObra, 2);
        }
        if ($dataAtual->format('Y-m') === $ultimoMesIncorporacao->format('Y-m')) {
            $custos['Incorporação RI'] = round($ri, 2);
        }
        if ($periodo === 'Entrega') {
            $custos['Incorporação Entrega'] = round($entrega, 2);
        }

        if ($periodo === 'Lançamento') {
            $custos['Obra (Lançamento)'] = round(($custoObraTotal * ($params['obraAteLancamento'] ?? 0.01)) / max(1, $params['mesesLancamento']), 2);
        }

        if ($periodo === 'Obra') {
            $inicioObra = $datas['dataLancamento']
                ->copy()
                ->addMonths((int) ($params['mesesLancamento'] ?? 0))
                ->startOfMonth();
            $mesObraIndex = (int) $inicioObra->diffInMonths($dataAtual->copy()->startOfMonth()) + 1;
            $curvaObra = $this->curvaService->getCurvaObraBaseParaPrazo((int) ($params['mesesObra'] ?? 0));
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

        if ($periodo === 'Entrega' || $periodo === 'Pós-Obra') {
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

    private function calcularDeducoesMensais(array $receitas, array $dadosProdutos, array $params): array
    {
        $receitaMes = (float) ($receitas['total'] ?? 0.0);
        $jurosCorrecaoMes = (float) ($receitas['juros_correcao'] ?? 0.0);
        if ($receitaMes <= 0) {
            return ['ret_imoveis' => 0.0, 'ret_lotes' => 0.0, 'iss' => 0.0, 'outras' => 0.0, 'total' => 0.0];
        }

        $baseSemJuros = max(0.0, $receitaMes - $jurosCorrecaoMes);
        $produtos = $dadosProdutos['produtos'] ?? [];

        if ($produtos === []) {
            $retImoveis = $receitaMes * (float) ($params['percentualPisCofins'] ?? 0.0);
            $iss = $receitaMes * (float) ($params['percentualIss'] ?? 0.0);
            $outras = $baseSemJuros * (float) ($params['percentualOutrosImpostos'] ?? 0.0);
            $total = $retImoveis + $iss + $outras;

            return ['ret_imoveis' => round($retImoveis, 2), 'ret_lotes' => 0.0, 'iss' => round($iss, 2), 'outras' => round($outras, 2), 'total' => round($total, 2)];
        }

        $vgvTotal = 0.0;
        foreach ($produtos as $produto) {
            $vgvTotal += (float) ($produto['vgv_produto'] ?? 0.0);
        }

        if ($vgvTotal <= 0) {
            return ['ret_imoveis' => 0.0, 'ret_lotes' => 0.0, 'iss' => 0.0, 'outras' => 0.0, 'total' => 0.0];
        }

        $retImoveis = 0.0;
        $retLotes = 0.0;
        $iss = 0.0;
        $outras = 0.0;

        foreach ($produtos as $produto) {
            $proporcao = ((float) ($produto['vgv_produto'] ?? 0.0)) / $vgvTotal;
            $receitaProdutoMes = $receitaMes * $proporcao;
            $baseSemJurosProduto = $baseSemJuros * $proporcao;
            $tributosPct = (float) ($produto['imposto_tributos'] ?? ($params['percentualPisCofins'] ?? 0.0));
            $issPct = (float) ($produto['imposto_iss'] ?? ($params['percentualIss'] ?? 0.0));
            $outrasPct = (float) ($produto['imposto_outros'] ?? ($params['percentualOutrosImpostos'] ?? 0.0));

            if ($this->ehProdutoLote($produto)) {
                $retLotes += $receitaProdutoMes * $tributosPct;
                continue;
            }

            $retImoveis += $receitaProdutoMes * $tributosPct;
            $iss += $receitaProdutoMes * $issPct;
            $outras += $baseSemJurosProduto * $outrasPct;
        }

        $total = $retImoveis + $retLotes + $iss + $outras;

        return [
            'ret_imoveis' => round($retImoveis, 2),
            'ret_lotes' => round($retLotes, 2),
            'iss' => round($iss, 2),
            'outras' => round($outras, 2),
            'total' => round($total, 2),
        ];
    }

    private function ehProdutoLote(array $produto): bool
    {
        $nome = strtolower((string) ($produto['nome'] ?? ''));

        return str_contains($nome, 'lote') || str_contains($nome, 'terreno');
    }

    private function calcularCustoTerreno(float $receitaMes, array $dadosProdutos, array $params): float
    {
        $totalCustoTerreno = (float) ($params['compraTerreno'] ?? 0);
        $receitaTotal = $dadosProdutos['vgvComCorrecao'] ?? $dadosProdutos['vgv'];

        return $receitaTotal > 0 ? ($totalCustoTerreno * $receitaMes) / $receitaTotal : 0;
    }

    private function calcularPagamentoTerreno(string $mes, string $periodo, array $receitas, array $dadosProdutos, array $datas, array $params, ViabilidadeFluxoContext $ctx): array
    {
        $parceria = max(0.0, ((float) ($receitas['total'] ?? 0.0)) * ((float) ($params['parceriaVgv'] ?? 0.0)));
        $compraTerrenoMensal = $this->calcularCompraTerrenoMensal($periodo, $params);
        $parceria += $compraTerrenoMensal;
        $permutaFisica = $this->calcularPagamentoPermutaFisicaTerreno($mes, $periodo, $dadosProdutos, $datas, $params);
        $comissaoCorretor = $this->calcularComissaoCorretorTerreno($mes, $dadosProdutos, $datas, $params, $ctx);
        $total = $parceria + $permutaFisica + $comissaoCorretor;

        return [
            'parceria' => $parceria,
            'permuta_fisica' => $permutaFisica,
            'comissao_corretor' => $comissaoCorretor,
            'total' => $total,
        ];
    }

    private function calcularCompraTerrenoMensal(string $periodo, array $params): float
    {
        if ($periodo !== 'Obra') {
            return 0.0;
        }

        return max(0.0, (float) ($params['compraTerreno'] ?? 0.0)) / max(1, (int) ($params['mesesObra'] ?? 1));
    }

    private function calcularPagamentoPermutaFisicaTerreno(string $mes, string $periodo, array $dadosProdutos, array $datas, array $params): float
    {
        $obraAteLancamento = max(0.0, (float) ($params['obraAteLancamento'] ?? 0.0));
        $percentualMes = 0.0;
        if ($periodo === 'Lançamento') {
            $percentualMes = $obraAteLancamento / max(1, (int) ($params['mesesLancamento'] ?? 1));
        }
        if ($periodo === 'Obra') {
            $dataAtual = Carbon::parse($mes.'-01');
            $mesObraIndex = (int) $datas['inicioObra']->diffInMonths($dataAtual) + 1;
            $curvaObra = $dadosProdutos['curvaObraAgregada'] ?? $this->agregarCurvaObra((int) ($params['mesesObra'] ?? 0));
            $percentualMes = (((float) ($curvaObra[$mesObraIndex - 1] ?? 0.0)) / 100) * max(0.0, 1 - $obraAteLancamento);
        }

        return $this->calcularCustoPermutaFisicaTotal($dadosProdutos) * max(0.0, $percentualMes);
    }

    private function calcularComissaoCorretorTerreno(string $mes, array $dadosProdutos, array $datas, array $params, ViabilidadeFluxoContext $ctx): float
    {
        $parcelamento = max(1, (int) ($params['parcelamentoComissaoTerreno'] ?? 1));
        $dataAtual = Carbon::parse($mes.'-01')->startOfMonth();
        $inicio = $datas['dataLancamento']->copy()->startOfMonth();
        $fim = $inicio->copy()->addMonths($parcelamento - 1);
        if (! $dataAtual->between($inicio, $fim)) {
            return 0.0;
        }

        $totalTerrenista = max(0.0, (float) ($params['compraTerreno'] ?? 0.0))
            + $this->calcularParceriaTerrenoTotal($dadosProdutos, $params, $ctx)
            + $this->calcularCustoPermutaFisicaTotal($dadosProdutos);

        return ($totalTerrenista * (float) ($params['percentualComissao'] ?? 0.0)) / $parcelamento;
    }

    private function calcularParceriaTerrenoTotal(array $dadosProdutos, array $params, ViabilidadeFluxoContext $ctx): float
    {
        $totalRecursosProprios = 0.0;
        $totalJurosCorrecao = 0.0;
        foreach ($ctx->recursosProprios as $recebimentos) {
            $totalRecursosProprios += ($recebimentos['sinal'] ?? 0.0) + ($recebimentos['parcelas_obra'] ?? 0.0) + ($recebimentos['parcelas_pos'] ?? 0.0);
            $totalJurosCorrecao += ($recebimentos['juros'] ?? 0.0) + ($recebimentos['correcao'] ?? 0.0) + ($recebimentos['correcao_obra'] ?? 0.0);
        }

        $totalRecursoTerrenos = 0.0;
        foreach (($dadosProdutos['produtos'] ?? []) as $produto) {
            $unidades = max(0, ((int) ($produto['quantidade_unidades'] ?? 0)) - ((int) ($produto['permutas'] ?? 0)));
            $avaliacao = (float) ($produto['avaliacao_lotesCef'] ?? 0.0);
            $preco = (float) ($produto['preco'] ?? 0.0);
            $totalRecursoTerrenos += $unidades * (($avaliacao > 0 && $avaliacao <= 1) ? ($avaliacao * $preco) : $avaliacao);
        }

        $totalEntradas = $totalRecursosProprios + $totalJurosCorrecao + $totalRecursoTerrenos + (float) $ctx->valorMedicaoTotal;

        return $totalEntradas * (float) ($params['parceriaVgv'] ?? 0.0);
    }

    private function calcularCustoPermutaFisicaTotal(array $dadosProdutos): float
    {
        $total = 0.0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            $permutas = (int) ($produto['permutas'] ?? 0);
            if ($permutas <= 0) {
                continue;
            }
            $total += $permutas * ((((float) ($produto['custo_m2'] ?? 0.0)) * ((float) ($produto['metragem'] ?? 0.0))) + ((float) ($produto['custo_infraestrutura'] ?? 0.0)));
        }

        return $total;
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

        $construcaoStandMesesAntesLancamento = max(0, (int) ($params['construcaoStandMesesAntesLancamento'] ?? 0));
        $inicioConstrucaoStand = $datas['dataLancamento']->copy()->subMonths($construcaoStandMesesAntesLancamento)->startOfMonth();
        $fimConstrucaoStand = $inicioConstrucaoStand->copy()->addMonths(max(1, (int) ($params['mesesLancamento'] ?? 1)) - 1)->startOfMonth();
        $standParcelado = $dataAtual->startOfMonth()->between($inicioConstrucaoStand, $fimConstrucaoStand)
            ? (($params['standVendas'] ?? 0) / max(1, (int) ($params['mesesLancamento'] ?? 1)))
            : 0;
        $gastosMensaisStand = $dataAtual->between($datas['dataLancamento'], $datas['fimObra']) ? ($vgvSemPermuta * ($params['gastosMensaisStand'] ?? 0)) : 0;
        $ajudaCustoGerentes = $dataAtual->between($datas['dataLancamento'], $datas['fimObra'])
            ? (($params['ajudaCustoGerente'] ?? 0) + ($params['ajudaCustoGerenteRegional'] ?? 0))
            : 0;
        $outrasDespesasComerciais = $dataAtual->between($datas['dataLancamento'], $datas['fimObra']) ? ($params['reembolsoLogistica'] ?? 0) : 0;

        $bonusEquipeComercial = 0.0;
        if (! $ctx->bonusEquipeComercialPago) {
            $totalUnidades = (float) ($dadosProdutos['totalUnidadesConstrutora'] ?? $dadosProdutos['totalUnidades'] ?? 0.0);
            if ($totalUnidades > 0 && $ctx->vendasAcumuladas >= $totalUnidades) {
                $bonusEquipeComercial = (float) ($params['bonusEquipeComercial'] ?? 0.0);
                $ctx->bonusEquipeComercialPago = true;
            }
        }

        $total = $standParcelado + $gastosMensaisStand + $comissaoVenda + $comissaoDesligamento +
            $ajudaCustoGerentes + $bonusCca + $outrasDespesasComerciais + $bonusEquipeComercial;

        return [
            'total' => $total,
            'detalhes' => [
                'Stand de Vendas' => $standParcelado,
                'Gastos Mensais Stand' => $gastosMensaisStand,
                'Comissão Venda' => $comissaoVenda,
                'Comissão Desligamento' => $comissaoDesligamento,
                'Ajuda de Custo Gerentes' => $ajudaCustoGerentes,
                'Bônus CCA' => $bonusCca,
                'Outras Despesas Comerciais' => $outrasDespesasComerciais,
                'Bônus Equipe Comercial' => $bonusEquipeComercial,
            ],
        ];
    }

    private function calcularComissaoDesligamentoMensal(
        string $mes,
        float $ticketMedio,
        array $params,
        ViabilidadeFluxoContext $ctx
    ): float {
        $dataAtual = Carbon::parse($mes.'-01')->startOfMonth();
        $taxaComissaoMedia = (($params['percentualVendasHouse'] ?? 0) * ($params['comissaoHousePercentual'] ?? 0)) +
            ((1 - ($params['percentualVendasHouse'] ?? 0)) * ($params['comissaoImobiliariasPercentual'] ?? 0));
        $percentualDesligamento = (float) ($params['pagamentoComissaoDesligamento'] ?? 0.0);
        $unidadesVendidasMes = (float) ($ctx->vendasPorMes[$mes] ?? 0.0);
        $valorVendaMes = $unidadesVendidasMes * $ticketMedio;
        $comissaoDesligamentoMes = $valorVendaMes * $taxaComissaoMedia * $percentualDesligamento;

        if (! $ctx->demandaAtingida || $ctx->mesDemandaAtingida === null) {
            $ctx->comissaoDesligamentoAcumulada += $comissaoDesligamentoMes;

            return 0.0;
        }

        $dataDemandaAtingida = Carbon::parse($ctx->mesDemandaAtingida.'-01')->startOfMonth();
        if ($dataAtual->lt($dataDemandaAtingida)) {
            $ctx->comissaoDesligamentoAcumulada += $comissaoDesligamentoMes;

            return 0.0;
        }

        if ($dataAtual->equalTo($dataDemandaAtingida) && ! $ctx->comissaoDesligamentoAcumuladaPaga) {
            $total = $ctx->comissaoDesligamentoAcumulada + $comissaoDesligamentoMes;
            $ctx->comissaoDesligamentoAcumulada = 0.0;
            $ctx->comissaoDesligamentoAcumuladaPaga = true;

            return $total;
        }

        return $comissaoDesligamentoMes;
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
        $dataAtual = Carbon::parse($mes.'-01')->startOfMonth();
        $inicioPos = $datas['inicioPos']->copy()->startOfMonth();
        $fimPos = $datas['fimPos']->copy()->startOfMonth();
        if ($dataAtual->lt($inicioPos) || $dataAtual->gt($fimPos)) {
            return 0.0;
        }

        $mesPosObra = (int) $inicioPos->diffInMonths($dataAtual) + 1;
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
