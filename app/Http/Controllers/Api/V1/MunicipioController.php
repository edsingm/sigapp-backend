<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MunicipioController extends Controller
{
    private const IBGE_BASE = 'https://servicodados.ibge.gov.br/api/v3/agregados';
    private const CACHE_TTL = 60 * 60 * 24 * 30; // 30 dias

    public function dadosSidra(string $ibgeCodigo): JsonResponse
    {
        if (! preg_match('/^\d{7}$/', $ibgeCodigo)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_CODE', 'message' => 'Código IBGE inválido.'],
            ], 422);
        }

        $cacheKey = "municipio:sidra:{$ibgeCodigo}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->fetchSidraData($ibgeCodigo));

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    private function fetchSidraData(string $ibgeCodigo): array
    {
        $loc = "N6[{$ibgeCodigo}]";

        $responses = Http::pool(fn ($pool) => [
            // População estimada (tabela 6579, variável 9324)
            $pool->as('populacao')
                ->timeout(10)
                ->get(self::IBGE_BASE . "/6579/periodos/2022/variaveis/9324?localidades={$loc}"),

            // PIB total e PIB per capita (tabela 5938)
            $pool->as('pib')
                ->timeout(10)
                ->get(self::IBGE_BASE . "/5938/periodos/2021/variaveis/37|614?localidades={$loc}"),

            // Área territorial (tabela 301, variável 606)
            $pool->as('area')
                ->timeout(10)
                ->get(self::IBGE_BASE . "/301/periodos/2022/variaveis/606?localidades={$loc}"),

            // Domicílios Censo 2022 (tabela 9514, variável 9344)
            $pool->as('domicilios')
                ->timeout(10)
                ->get(self::IBGE_BASE . "/9514/periodos/2022/variaveis/9344?localidades={$loc}"),
        ]);

        $populacaoRows = $this->parseRows(
            $this->safeJson($responses['populacao']),
            $ibgeCodigo
        );

        $pibRows = $this->parseRows(
            $this->safeJson($responses['pib']),
            $ibgeCodigo
        );

        $areaRows = $this->parseRows(
            $this->safeJson($responses['area']),
            $ibgeCodigo
        );

        $domiciliosRows = $this->parseRows(
            $this->safeJson($responses['domicilios']),
            $ibgeCodigo
        );

        return [
            'territorial' => [
                'area_densidade' => $areaRows ?: null,
                'populacao_estimada' => $populacaoRows ?: null,
            ],
            'infraestrutura' => [
                'domicilios' => $domiciliosRows ?: null,
                'domicilios_desocupados' => null,
                'esgoto' => null,
            ],
            'mercado' => [
                'pib' => $pibRows ?: null,
            ],
            'socioeconomico' => [
                'desocupacao' => null,
            ],
        ];
    }

    /**
     * Transforma a resposta da API SIDRA v3 em linhas no formato esperado pelo frontend.
     *
     * Cada elemento do array de resposta IBGE representa uma variável.
     * Cada variável pode ter múltiplos resultados (classificações/categorias).
     *
     * @param  array<mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function parseRows(array $data, string $ibgeCodigo): array
    {
        $rows = [];

        foreach ($data as $variavel) {
            $label = $variavel['variavel'] ?? null;
            $unidade = $variavel['unidade'] ?? null;

            foreach ($variavel['resultados'] ?? [] as $resultado) {
                // Identificar classificação D3 (quando houver)
                $d3 = null;
                $classificacoes = $resultado['classificacoes'] ?? [];
                if (! empty($classificacoes)) {
                    $primeiraCategoria = array_key_first($classificacoes[0]['categoria'] ?? []);
                    $d3 = $classificacoes[0]['categoria'][$primeiraCategoria] ?? null;
                }

                foreach ($resultado['series'] ?? [] as $serie) {
                    if (($serie['localidade']['id'] ?? null) !== $ibgeCodigo) {
                        continue;
                    }

                    $serieData = $serie['serie'] ?? [];
                    $periodo = array_key_last($serieData);
                    $valor = $serieData[$periodo] ?? null;

                    if ($valor === null || $valor === '-') {
                        continue;
                    }

                    $rows[] = [
                        'codigo' => $variavel['id'] ?? null,
                        'label' => $label,
                        'valor' => $valor,
                        'unidade' => $unidade,
                        'periodo' => $periodo,
                        'd3' => $d3,
                        'd4' => null,
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @return array<mixed>
     */
    private function safeJson(mixed $response): array
    {
        try {
            if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Throwable) {
            // Falha silenciosa: retorna array vazio para o campo correspondente
        }

        return [];
    }
}
