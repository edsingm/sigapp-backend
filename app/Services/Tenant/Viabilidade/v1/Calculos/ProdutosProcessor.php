<?php

namespace App\Services\Tenant\Viabilidade\v1\Calculos;

use App\Models\Tenant\Produto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Services\Tenant\Viabilidade\v1\ImpostosService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ProdutosProcessor
{
    public function __construct(
        private readonly ImpostosService $impostosService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @param  list<array<string, mixed>>|null  $customProdutos
     * @return array<string, mixed>
     */
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

        /** @var Collection<int, TerrenoProduto> $terrenoProdutos */
        $terrenoProdutos = $terreno->terrenoProdutos()->with('produto')->get();

        foreach ($terrenoProdutos as $terrenoProduto) {
            $produto = $terrenoProduto->produto()->first();
            if (! $produto instanceof Produto) {
                continue;
            }
            $cp = $customMap[$terrenoProduto->getKey()] ?? [];

            $unidades = max(0, (int) ($cp['unidades'] ?? $terrenoProduto->unidades ?? 0));
            $valor = (float) ($cp['valor'] ?? $terrenoProduto->valor ?? 0);
            $permutas = max(0, (int) ($cp['permuta'] ?? $terrenoProduto->permuta ?? 0));
            $pgtoPorLote = max(0.0, (float) ($cp['pgto_por_lote'] ?? $terrenoProduto->pgto_por_lote ?? 0));

            // Permuta nunca pode superar unidades; zero comercializável é estado válido.
            if ($permutas > $unidades) {
                $permutas = $unidades;
            }

            $avaliacaoLotesCef = $produto->avaliacao_lotesCef ?? 0;
            $custoM2 = $cp['custo_m2'] ?? $produto->m2_cost ?? 0;
            $custoInfra = $cp['custo_infra'] ?? $produto->infra_cost ?? 0;
            $areaPrivativa = $produto->private_area ?? 0;
            $demandaMinCef = $produto->demanda_minCef ?? 0;

            $unidadesComercializaveis = max(0, $unidades - $permutas);

            $dados['totalUnidades'] += $unidades;
            $dados['permutas'] += $permutas;
            $dados['totalUnidadesConstrutora'] += $unidadesComercializaveis;

            $vgvProduto = $valor * $unidades;
            $dados['vgv'] += $vgvProduto;
            $dados['areaConstruida'] += $areaPrivativa * $unidades;

            $vgvSemPermuta = $valor * $unidadesComercializaveis;
            $vgvSemTerrenista = $vgvSemPermuta - ($unidades * $pgtoPorLote);
            $dados['vgvSemUnidPermutas'] += $vgvSemPermuta;
            $dados['vgvSemValorTerrenista'] += max(0.0, $vgvSemTerrenista);

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

            $dados['custoObraHabitacao'] += $custoM2 * $areaPrivativa * $unidadesComercializaveis;
            $dados['custoInfraestrutura'] += $custoInfra * $unidadesComercializaveis;

            $dados['produtos'][] = [
                'id' => $produto->getKey(),
                'terreno_produto_id' => $terrenoProduto->getKey(),
                'nome' => (string) $produto->getAttribute('name'),
                'preco' => $valor,
                'metragem' => $areaPrivativa,
                'quantidade_unidades' => $unidades,
                'custo_m2' => $custoM2,
                'custo_infraestrutura' => $custoInfra,
                'custo_infra_por_lote' => $custoInfra,
                'custo_infra' => $custoInfra,
                'vgv_produto' => $vgvProduto,
                'avaliacao_lotesCef' => $avaliacaoLotesCef,
                'defasagem_pgtoTerreno' => $produto->defasagem_pgtoTerreno ?? 0,
                'permutas' => $permutas,
                'pgto_por_lote' => $pgtoPorLote,
                'demanda_minCef' => $demandaMinCef,
                'curva_vendas' => $produto->curva_vendas ?? [],
                'baloes_anuais' => $produto->baloes_anuais ?? [],
                'balao_entrega_modo' => $produto->balao_entrega_modo ?? 'saldo_restante',
                'imposto_tributos' => $params['percentualPisCofins'] ?? 0,
                'imposto_iss' => $params['percentualIss'] ?? 0,
                'imposto_outros' => $params['percentualOutrosImpostos'] ?? 0,
                'custo_contratacao_cef' => $params['custoMedicaoContratacao'] ?? $params['custoContratacaoCef'] ?? 0,
                'pj_taxa_juros' => $params['taxaJurosPj'] ?? 0.105,
                'assist_tecnica_curva' => $this->extrairAssistenciaTecnicaProduto($produto),
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

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $dadosProdutos
     * @return array<string, mixed>
     */
    public function mesclarParametros(array $params, array $dadosProdutos): array
    {
        $produtos = $dadosProdutos['produtos'] ?? [];
        if (empty($produtos)) {
            return $params;
        }

        $extrair = function (string $campo, mixed $fallback) use ($produtos): mixed {
            $primeiro = $produtos[0][$campo] ?? null;
            if (is_array($primeiro) && ! empty($primeiro)) {
                return $primeiro;
            }

            $somaPonderada = 0.0;
            $unidadesComValor = 0;
            foreach ($produtos as $produto) {
                $valor = $produto[$campo] ?? null;
                if ($valor !== null && $valor != 0 && ! is_array($valor)) {
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
                if ($valor !== null && $valor != 0 && ! is_array($valor)) {
                    return $valor;
                }
            }

            return $fallback;
        };

        // Produto continua podendo definir apenas a curva de assistencia tecnica,
        // que ainda e tratada por tipologia. Parametros globais ficam em
        // viabilidades/premissas_viabilidade.
        $params['assistenciaTecnicaCurva'] = $extrair('assist_tecnica_curva', $params['assistenciaTecnicaCurva'] ?? [50, 20, 10, 10, 10]);

        return $params;
    }

    /**
     * Curva anual de assistência técnica (pós-obra), em escala 0–100.
     *
     * No cadastro de produto os campos `assist_tecnica1..5` são persistidos
     * como fração (0.5 = 50%). O motor de fluxo divide por 100 ao aplicar a
     * curva; portanto precisamos normalizar para pontos percentuais.
     *
     * @return list<float>
     */
    private function extrairAssistenciaTecnicaProduto(mixed $produto): array
    {
        $padrao = [50.0, 20.0, 10.0, 10.0, 10.0];
        $raw = [
            (float) ($produto->assist_tecnica1 ?? 0),
            (float) ($produto->assist_tecnica2 ?? 0),
            (float) ($produto->assist_tecnica3 ?? 0),
            (float) ($produto->assist_tecnica4 ?? 0),
            (float) ($produto->assist_tecnica5 ?? 0),
        ];

        if (array_sum($raw) <= 0) {
            return $padrao;
        }

        // Fração (0–1): converte para 0–100. Valores já em 0–100 permanecem.
        $max = max($raw);
        if ($max <= 1.0) {
            return array_map(
                static fn (float $value): float => round($value * 100, 6),
                $raw
            );
        }

        return $raw;
    }
}
