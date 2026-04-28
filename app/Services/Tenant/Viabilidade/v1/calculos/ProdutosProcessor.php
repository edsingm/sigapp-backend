<?php

namespace App\Services\Tenant\Viabilidade\v1\Calculos;

use App\Models\Tenant\Terreno;
use App\Services\Tenant\Viabilidade\v1\ImpostosService;
use Carbon\Carbon;

class ProdutosProcessor
{
    public function __construct(
        private readonly ImpostosService $impostosService,
    ) {}

    public function processar(Terreno $terreno, array $params, ?array $customProdutos): array
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

            $imps = $this->impostosService->calcularImpostosProduto(
                $vgvSemTerrenista,
                ($params['percentualPisCofins'] ?? 0) * 100,
                ($params['percentualIss'] ?? 0) * 100,
                ($params['percentualOutrosImpostos'] ?? 0) * 100
            );
            $dados['imposto_pis'] += $imps['imposto_pis'];
            $dados['imposto_cofins'] += $imps['imposto_cofins'];
            $dados['imposto_iss'] += $imps['imposto_iss'];

            $impostoTributosRaw = ($params['percentualPisCofins'] ?? 0) * 100;
            if ($impostoTributosRaw > 5) {
                $irpjCorrigido = $vgvProduto * 0.012;
                $csllCorrigido = $vgvProduto * 0.0108;
            } else {
                $valorImposto = $vgvProduto * ($impostoTributosRaw / 100);
                $irpjCorrigido = $valorImposto * 0.315;
                $csllCorrigido = $valorImposto * 0.165;
            }
            $dados['irrpj'] += $irpjCorrigido;
            $dados['csll'] += $csllCorrigido;

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
                'imposto_tributos' => $params['percentualPisCofins'] ?? 0,
                'imposto_iss' => $params['percentualIss'] ?? 0,
                'imposto_outros' => $params['percentualOutrosImpostos'] ?? 0,
                'gastos_mensais_stand' => (float) ($produto->gastos_mensaisStand ?? 0),
                'comissao_house' => (float) ($produto->comissao_house ?? 0) / 100,
                'porcentagem_comissao_house' => (float) ($produto->porcentagem_comissaoHouse ?? 0) / 100,
                'porcentagem_comissao_imobs' => (float) ($produto->porcentagem_comissaoImobs ?? 0) / 100,
                'pagto_comissao_venda' => (float) ($produto->pagto_comissaoNaVenda ?? 0) / 100,
                'marketing_lancamento' => (float) ($produto->marketing_lancamento ?? 0) / 100,
                'marketing_antes_lancamento' => (int) ($produto->marketing_antesLancamento ?? 0),
                'custo_contratacao_cef' => $params['custoMedicaoContratacao'] ?? $params['custoContratacaoCef'] ?? 0,
                'pj_taxa_juros' => $params['taxaJurosPj'] ?? 0.105,
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
                    'irrpj' => $irpjCorrigido,
                    'csll' => $csllCorrigido,
                ],
            ];

            $dados['custoCasaM2'] = $custoM2 * $areaPrivativa;
            $dados['custoInfraM2'] = $custoInfra;
        }

        $dados['correcaoSobreVgv'] = 0;
        $dados['vgvComCorrecao'] = $dados['vgvSemValorTerrenista'];
        $dados['custoNaoIncidente'] = $params['infraNaoIncidente'] * $dados['vgv'];
        $dados['dataInicio'] = $params['dataLancamento'] ?? Carbon::now()->addYears(2);

        return $dados;
    }

    public function mesclarParametros(array $params, array $dadosProdutos): array
    {
        $produtos = $dadosProdutos['produtos'] ?? [];
        if (empty($produtos)) {
            return $params;
        }

        $extrair = function (string $campo, mixed $fallback) use ($produtos): mixed {
            $primeiro = $produtos[0][$campo] ?? null;
            if (is_array($primeiro) && !empty($primeiro)) {
                return $primeiro;
            }

            $somaPonderada = 0.0;
            $unidadesComValor = 0;
            foreach ($produtos as $produto) {
                $valor = $produto[$campo] ?? null;
                if ($valor !== null && $valor != 0 && !is_array($valor)) {
                    $unidades = (int) ($produto['quantidade_unidades'] ?? 0);
                    $somaPonderada += (float) $valor * $unidades;
                    $unidadesComValor += $unidades;
                }
            }
            if ($unidadesComValor > 0) {
                return $somaPonderada / $unidadesComValor;
            }
            foreach ($produtos as $produto) {
                $valor = $produto[$campo] ?? null;
                if ($valor !== null && $valor != 0 && !is_array($valor)) {
                    return $valor;
                }
            }

            return $fallback;
        };

        $params['gastosMensaisStand'] = (float) $extrair('gastos_mensais_stand', $params['gastosMensaisStand'] ?? 0.0001);
        $params['comissaoHousePercentual'] = (float) $extrair('comissao_house', $params['comissaoHousePercentual'] ?? 0.03);
        $params['percentualVendasHouse'] = (float) $extrair('porcentagem_comissao_house', $params['percentualVendasHouse'] ?? 0.50);
        $params['comissaoImobiliariasPercentual'] = $params['comissaoImobiliariasPercentual'] ?? 0.035;
        $params['pagamentoComissaoVenda'] = (float) $extrair('pagto_comissao_venda', $params['pagamentoComissaoVenda'] ?? 0.50);
        $params['marketingLancamento'] = (float) $extrair('marketing_lancamento', $params['marketingLancamento'] ?? 0.25);
        $params['marketingInicioAntesLancamento'] = (int) $extrair('marketing_antes_lancamento', $params['marketingInicioAntesLancamento'] ?? 3);
        $params['assistenciaTecnicaCurva'] = $extrair('assist_tecnica_curva', $params['assistenciaTecnicaCurva'] ?? [50, 20, 10, 10, 10]);
        $params['incorporacaoRi'] = (float) $extrair('incorp_ri', $params['incorporacaoRi'] ?? 0.30);
        $params['incorporacaoEntrega'] = (float) $extrair('incorp_entrega', $params['incorporacaoEntrega'] ?? 0.15);
        $params['incorporacaoAteLancamento'] = (float) $extrair('incorp_ate_lancamento', $params['incorporacaoAteLancamento'] ?? 0.80);
        $params['obraAteLancamento'] = (float) $extrair('obra_ateLancamento', $params['obraAteLancamento'] ?? 0.01);

        return $params;
    }

    private function extrairAssistenciaTecnicaProduto(mixed $produto): array
    {
        return [
            (float) ($produto->assist_tecnica1 ?? 50),
            (float) ($produto->assist_tecnica2 ?? 20),
            (float) ($produto->assist_tecnica3 ?? 10),
            (float) ($produto->assist_tecnica4 ?? 10),
            (float) ($produto->assist_tecnica5 ?? 10),
        ];
    }
}
