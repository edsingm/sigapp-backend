<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ViabilidadeCalculationResource extends JsonResource
{
    /**
     * @var list<string>
     */
    private const DEFAULT_INCLUDES = [
        'resumo',
        'indicadores',
        'produtos_resumo',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{viabilidade?: mixed, dre_resultados?: mixed} $payload */
        $payload = is_array($this->resource) ? $this->resource : [];
        $viabilidade = $payload['viabilidade'] ?? null;
        $dreResultados = is_array($payload['dre_resultados'] ?? null) ? $payload['dre_resultados'] : [];
        $include = $this->parseInclude($request);

        $response = [
            'viabilidade' => $viabilidade !== null ? new ViabilidadeResource($viabilidade) : null,
            'resumo' => $this->buildResumo($dreResultados),
            'indicadores' => $this->buildIndicadores($dreResultados),
            'produtos_resumo' => $this->buildProdutosResumo($dreResultados),
        ];

        if ($this->shouldInclude($include, 'dre')) {
            $response['dre'] = $dreResultados['dre_itens'] ?? [];
        }

        if ($this->shouldInclude($include, 'dre_caixa')) {
            $response['dre_caixa'] = $dreResultados['dre_caixa'] ?? [];
        }

        if ($this->shouldInclude($include, 'dre_contabil_poc')) {
            $response['dre_contabil_poc'] = $dreResultados['dre_contabil_poc'] ?? [];
        }

        if ($this->shouldInclude($include, 'dre_contabil_poc_mensal')) {
            $response['dre_contabil_poc_mensal'] = $dreResultados['dre_contabil_poc_mensal'] ?? [];
        }

        if ($this->shouldInclude($include, 'dre_contabil_poc_mensal_blocos')) {
            $response['dre_contabil_poc_mensal_blocos'] = $dreResultados['dre_contabil_poc_mensal_blocos'] ?? [];
        }

        if ($this->shouldInclude($include, 'ponte_reconciliacao')) {
            $response['ponte_reconciliacao'] = $dreResultados['ponte_reconciliacao'] ?? [];
        }

        if ($this->shouldInclude($include, 'fluxo_mensal')) {
            $response['fluxo_mensal'] = $dreResultados['fluxo_mensal'] ?? [];
        }

        if ($this->shouldInclude($include, 'fluxo_mensal_financeiro')) {
            $response['fluxo_mensal_financeiro'] = $dreResultados['fluxo_mensal_financeiro'] ?? [];
        }

        if ($this->shouldInclude($include, 'totais')) {
            $response['totais'] = $dreResultados['totais'] ?? [];
        }

        if ($this->shouldInclude($include, 'dados_produtos')) {
            $response['dados_produtos'] = $dreResultados['dados_produtos'] ?? [];
        }

        if ($this->shouldInclude($include, 'parametros_utilizados')) {
            $response['parametros_utilizados'] = $dreResultados['parametros_utilizados'] ?? [];
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $dreResultados
     * @return array<string, mixed>
     */
    private function buildResumo(array $dreResultados): array
    {
        $dre = is_array($dreResultados['dre_itens'] ?? null) ? $dreResultados['dre_itens'] : [];

        return [
            'vgv' => $dreResultados['vgv'] ?? null,
            'receita_liquida' => $dre['receita_liquida'] ?? null,
            'custos_diretos' => $dre['custos_diretos_total'] ?? null,
            'despesas_operacionais' => $dre['despesas_operacionais_total'] ?? null,
            'lucro_liquido' => $dre['lucro_liquido_projeto'] ?? null,
            'custo_total_projeto' => $dre['custo_total_projeto'] ?? ($dreResultados['custoTotal'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $dreResultados
     * @return array<string, mixed>
     */
    private function buildIndicadores(array $dreResultados): array
    {
        $indicadores = $dreResultados['indicadores'] ?? [];

        return is_array($indicadores) ? $indicadores : [];
    }

    /**
     * @param  array<string, mixed>  $dreResultados
     * @return list<array<string, mixed>>
     */
    private function buildProdutosResumo(array $dreResultados): array
    {
        $produtos = $dreResultados['produtos'] ?? [];
        if (! is_array($produtos)) {
            return [];
        }

        return collect($produtos)->map(function (mixed $produto): array {
            if (! is_array($produto)) {
                return [];
            }

            return [
                'terreno_produto_id' => $produto['terreno_produto_id'] ?? null,
                'produto_id' => $produto['id'] ?? null,
                'nome' => $produto['nome'] ?? null,
                'quantidade_unidades' => $produto['quantidade_unidades'] ?? null,
                'permutas' => $produto['permutas'] ?? null,
                'preco' => $produto['preco'] ?? null,
                'vgv_produto' => $produto['vgv_produto'] ?? null,
                'metragem' => $produto['metragem'] ?? null,
            ];
        })->filter()->values()->all();
    }

    /**
     * @return list<string>
     */
    private function parseInclude(Request $request): array
    {
        $raw = $request->query('include');
        if (! is_string($raw) || $raw === '') {
            return self::DEFAULT_INCLUDES;
        }

        return collect(array_merge(
            self::DEFAULT_INCLUDES,
            explode(',', $raw)
        ))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $include
     */
    private function shouldInclude(array $include, string $key): bool
    {
        return in_array('*', $include, true) || in_array($key, $include, true);
    }
}
