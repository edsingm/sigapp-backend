<?php

namespace App\Services\Tenant\Viabilidade\v1\Calculos;

use App\Services\Tenant\Viabilidade\v1\ImpostosService;

class DreCalculator
{
    public function __construct(
        private readonly ImpostosService $impostosService,
    ) {}

    public function calcular(array $fluxo, array $dadosProdutos, array $params): array
    {
        $vgv = $dadosProdutos['vgv'];
        $vgvSemTerrenista = $dadosProdutos['vgvSemValorTerrenista'];
        $vgvSemPermutas = $dadosProdutos['vgvSemUnidPermutas'];
        $totalUnidades = $dadosProdutos['totalUnidades'];

        // Mantido na assinatura para preservar o contrato atual do service.
        unset($fluxo);

        // Receitas
        $receitaTotalVendas = $vgvSemTerrenista;
        $jurosCorrecoes = $dadosProdutos['correcaoSobreVgv'];
        $receitaBruta = $receitaTotalVendas + $jurosCorrecoes;

        $impostos = $this->impostosService->calcularImpostosDre(
            $dadosProdutos['produtos'],
            $receitaBruta,
            $vgvSemTerrenista
        );
        $receitaLiquida = $receitaBruta - $impostos['total'];

        // Custos diretos
        $dadosProdutos['receita_bruta_dre'] = $receitaBruta;
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
            $custoTotalObra,
        ] = $this->calcularCustosDiretosDre($dadosProdutos, $params, $totalUnidades, $vgv);

        $lucroBruto = $receitaLiquida - $custosDiretosTotal;

        // Despesas operacionais
        [
            $despesasComerciais,
            $marketingTotal,
            $itbiIptu,
            $registroTotal,
            $txMedicao,
            $contratosCef,
            $produtosCef,
            $despesasOperacionaisTotal,
        ] = $this->calcularDespesasOperacionaisDre($dadosProdutos, $params);

        $ebitda = $lucroBruto - $despesasOperacionaisTotal;

        // Despesas financeiras
        $outrasDespFinanceiras = $params['percentualOutrasDespesasFinanceiras'] * $receitaTotalVendas;
        $despesasOnerosas = $jurosPJ['juros_totais'];
        $ebit = $ebitda - $outrasDespFinanceiras - $despesasOnerosas;

        // Resultado
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

    public function calcularSegurosPorTipologia(array $dadosProdutos, array $params): float
    {
        $total = 0.0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            $base = $this->ehProdutoLote($produto)
                ? $this->calcularBaseSemTerrenistaProduto($produto)
                : ($produto['vgv_produto'] ?? 0);
            $total += $base * ($params['percentualSeguros'] ?? 0);
        }

        return $total;
    }

    /**
     * @return array{0:float,1:float,2:float,3:float,4:float,5:float,6:float,7:float,8:float,9:float,10:float,11:array,12:float,13:float}
     */
    private function calcularCustosDiretosDre(
        array $dadosProdutos,
        array $params,
        int $totalUnidades,
        float $vgv
    ): array {
        $custoCompraTerreno = $this->calcularCompraTerreno($dadosProdutos, $params, $totalUnidades);
        $custoConstrucaoPermutas = $this->calcularCustoConstrucaoPermutas($dadosProdutos);
        $custoProprietario = $this->calcularCustoProprietario($dadosProdutos, $params, $totalUnidades, $vgv);
        $vgvBaseParceria = $dadosProdutos['receita_bruta_dre'] ?? ($dadosProdutos['vgv'] ?? $vgv);
        $parceriaVgv = $params['parceriaVgv'] * max(0, $vgvBaseParceria);

        $custoTerreno = $custoCompraTerreno
            + $parceriaVgv
            + $custoConstrucaoPermutas
            + $custoProprietario;

        $comissao = 0.01 * abs($custoTerreno);
        $incorporacao = $params['percentualIncorporacao'] * $vgv;
        $infraCasas = $dadosProdutos['custoObraHabitacao'];
        $infraLotes = $dadosProdutos['custoInfraestrutura'] + $dadosProdutos['custoNaoIncidente'];
        $areaComum = $params['custoAreaComum'] * $totalUnidades;
        $contrapartidas = $params['percentualContrapartidas'] * $vgv;
        $canteiroTotal = $params['canteiroMensal'] * $params['mesesObra'];
        $moAdministrativaTotal = $params['moAdministrativa'] * $params['mesesObra'];
        $seguros = $this->calcularSegurosPorTipologia($dadosProdutos, $params);

        $custoTotalObra = $infraCasas + $infraLotes + $areaComum + $contrapartidas + $canteiroTotal;

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

    private function calcularCustoConstrucaoPermutas(array $dadosProdutos): float
    {
        $total = 0.0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            $permutas = (int) ($produto['permutas'] ?? 0);
            if ($permutas <= 0) {
                continue;
            }

            $custoM2 = (float) ($produto['custo_m2'] ?? 0);
            $metragem = (float) ($produto['metragem'] ?? 0);
            $custoInfra = (float) ($produto['custo_infraestrutura'] ?? 0);
            $total += $permutas * (($custoM2 * $metragem) + $custoInfra);
        }

        return $total;
    }

    private function calcularCustoProprietario(array $dadosProdutos, array $params, int $totalUnidades, float $vgv): float
    {
        $p = $params['porcentagemLoteProprietario'] ?? 0.10;
        if ($p <= 0 || $p >= 1) {
            return 0.0;
        }

        unset($totalUnidades, $vgv);

        $total = 0.0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            $unidades = $produto['quantidade_unidades'] ?? 0;
            $custoInfraLotes = $produto['custo_infraestrutura'] ?? 0;

            $lotesProprietario = $unidades * ($p / (1 - $p));
            $total += $lotesProprietario * $custoInfraLotes;
        }

        return $total;
    }

    private function calcularCompraTerreno(array $dadosProdutos, array $params, int $totalUnidades): float
    {
        $valorPagamentoTotal = $params['compraTerreno'] ?? 0;
        if ($totalUnidades <= 0) {
            return 0.0;
        }

        $total = 0.0;
        $valorPorUnidade = $valorPagamentoTotal / $totalUnidades;

        foreach ($dadosProdutos['produtos'] as $produto) {
            $unidades = $produto['quantidade_unidades'] ?? 0;
            $total += $valorPorUnidade * $unidades;
        }

        return $total;
    }

    /**
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
        $vgvSemPermuta = $dadosProdutos['vgvSemUnidPermutas'] ?? 0.0;
        $total = $vgvSemPermuta * ($params['percentualDespesasComerciais'] ?? 0);

        return [
            'total' => $total,
            'detalhes' => [
                'despesas_comerciais' => round($total, 2),
            ],
        ];
    }

    private function calcularMarketingDetalhado(array $dadosProdutos, array $params): float
    {
        $base = ($dadosProdutos['vgvSemUnidPermutas'] ?? 0) * ($params['percentualMarketing'] ?? 0);
        $fatorLancamento = $params['marketingLancamento'] ?? 0;
        if ($fatorLancamento <= 0 || $fatorLancamento >= 1) {
            return $base;
        }

        $lancamento = $base * $fatorLancamento;
        $distribuido = $base * (1 - $fatorLancamento);

        return $lancamento + $distribuido;
    }

    private function calcularItbiPorTipologia(array $dadosProdutos, array $params): float
    {
        $total = 0.0;
        foreach ($dadosProdutos['produtos'] as $produto) {
            if ($this->ehProdutoLote($produto)) {
                continue;
            }

            $vgvSemPermuta = ($produto['vgv_produto'] ?? 0) - (($produto['permutas'] ?? 0) * ($produto['preco'] ?? 0));
            $total += $vgvSemPermuta * ($params['custoItbiIptu'] ?? 0);
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

        $total = 0.0;
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
}
