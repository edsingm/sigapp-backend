<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class AiIbgeCityProfileService
{
    private const CACHE_TTL = 60 * 60 * 24 * 7;

    private const LOCALIDADES_BASE = 'https://servicodados.ibge.gov.br/api/v1/localidades';

    private const PESQUISAS_BASE = 'https://servicodados.ibge.gov.br/api/v1/pesquisas';

    private const BIBLIOTECA_BASE = 'https://servicodados.ibge.gov.br/api/v1/biblioteca';

    private const CIDADES_BASE = 'https://www.ibge.gov.br/cidades-e-estados';

    /**
     * @return array<string, mixed>
     */
    public function getCityProfile(
        ?string $codigoMunicipio = null,
        ?string $cidade = null,
        ?string $uf = null
    ): array {
        $cacheKey = sprintf(
            'ai:ibge-city-profile:%s:%s:%s',
            $codigoMunicipio ?: 'null',
            Str::slug((string) $cidade),
            Str::lower((string) $uf)
        );

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn (): array => $this->buildProfile($codigoMunicipio, $cidade, $uf)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProfile(
        ?string $codigoMunicipio,
        ?string $cidade,
        ?string $uf
    ): array {
        $municipio = $this->resolveMunicipio($codigoMunicipio, $cidade, $uf);
        $codigo = (string) ($municipio['id'] ?? '');

        if ($codigo === '') {
            throw new RuntimeException('Não foi possível resolver o município no IBGE.');
        }

        $ufSigla = Str::lower((string) data_get($municipio, 'microrregiao.mesorregiao.UF.sigla'));
        $cidadeSlug = Str::slug((string) ($municipio['nome'] ?? ''));
        $panoramaUrl = self::CIDADES_BASE."/{$ufSigla}/{$cidadeSlug}.html";

        $historico = $this->fetchHistorico($codigo);
        $panorama = $this->fetchPanorama($panoramaUrl);

        $populacaoEstimada = $this->fetchIndicador($codigo, 33, '2025', 29171);
        $pibPerCapita = $this->fetchIndicador($codigo, 38, '2023', 47001);
        $pibTotal = $this->fetchIndicador($codigo, 38, '2023', 46997);
        $pessoalOcupado = $this->fetchIndicador($codigo, 19, '2023', 143514);
        $pessoalAssalariado = $this->fetchIndicador($codigo, 19, '2023', 143536);
        $salarioMedio = $this->fetchIndicador($codigo, 19, '2023', 143558);
        $domiciliosRecenseados = $this->fetchIndicador($codigo, 23, '2010', 27664);
        $domiciliosOcupados = $this->fetchIndicador($codigo, 23, '2010', 27658);
        $mediaMoradores = $this->fetchIndicador($codigo, 23, '2010', 27744);
        $domiciliosInternet = $this->fetchIndicador($codigo, 23, '2010', 28844);
        $rendaPerCapita = $this->fetchIndicador($codigo, 45, '2025', 288790);

        return [
            'municipio' => [
                'codigo_ibge' => (int) $codigo,
                'nome' => $municipio['nome'] ?? null,
                'uf' => data_get($municipio, 'microrregiao.mesorregiao.UF.sigla'),
                'estado' => data_get($municipio, 'microrregiao.mesorregiao.UF.nome'),
                'regiao' => data_get($municipio, 'microrregiao.mesorregiao.UF.regiao.nome'),
                'mesorregiao' => data_get($municipio, 'microrregiao.mesorregiao.nome'),
                'microrregiao' => data_get($municipio, 'microrregiao.nome'),
                'regiao_imediata' => data_get($municipio, 'regiao-imediata.nome'),
                'regiao_intermediaria' => data_get($municipio, 'regiao-imediata.regiao-intermediaria.nome'),
                'pagina_ibge' => $panoramaUrl,
            ],
            'panorama' => [
                'indicadores_rapidos' => $panorama,
                'destaques' => [
                    'populacao_estimada' => $populacaoEstimada,
                    'pib_per_capita' => $pibPerCapita,
                    'pib_total' => $pibTotal,
                    'renda_per_capita_domiciliar' => $rendaPerCapita ?: [
                        'indicador' => 'Rendimento domiciliar per capita',
                        'valor' => null,
                        'observacao' => 'O endpoint público consultado não retornou resultado para este município.',
                    ],
                ],
            ],
            'trabalho_e_renda' => [
                'pessoal_ocupado' => $pessoalOcupado,
                'pessoal_ocupado_assalariado' => $pessoalAssalariado,
                'salario_medio_mensal' => $salarioMedio,
                'renda_per_capita_domiciliar' => $rendaPerCapita,
            ],
            'habitacao' => [
                'domicilios_recenseados' => $domiciliosRecenseados,
                'domicilios_particulares_ocupados' => $domiciliosOcupados,
                'media_moradores_por_domicilio' => $mediaMoradores,
                'domicilios_com_internet' => $domiciliosInternet,
            ],
            'historico' => [
                'resumo' => $historico['HISTORICO'] ?? null,
                'formacao_administrativa' => $historico['FORMACAO_ADMINISTRATIVA'] ?? null,
                'gentilico' => $historico['GENTILICO'] ?? null,
                'fonte' => $historico['HISTORICO_FONTE'] ?? null,
            ],
            'fontes' => [
                'biblioteca' => self::BIBLIOTECA_BASE.'?aspas=3&codmun='.$codigo.'&tipoRetorno=json',
                'pagina_panorama' => $panoramaUrl,
                'indicadores' => [
                    $this->buildIndicadorUrl($codigo, 33, '2025', 29171),
                    $this->buildIndicadorUrl($codigo, 38, '2023', 47001),
                    $this->buildIndicadorUrl($codigo, 38, '2023', 46997),
                    $this->buildIndicadorUrl($codigo, 19, '2023', 143514),
                    $this->buildIndicadorUrl($codigo, 19, '2023', 143536),
                    $this->buildIndicadorUrl($codigo, 19, '2023', 143558),
                    $this->buildIndicadorUrl($codigo, 23, '2010', 27664),
                    $this->buildIndicadorUrl($codigo, 23, '2010', 27658),
                    $this->buildIndicadorUrl($codigo, 23, '2010', 27744),
                    $this->buildIndicadorUrl($codigo, 23, '2010', 28844),
                    $this->buildIndicadorUrl($codigo, 45, '2025', 288790),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMunicipio(
        ?string $codigoMunicipio,
        ?string $cidade,
        ?string $uf
    ): array {
        if ($codigoMunicipio !== null && preg_match('/^\d{7}$/', $codigoMunicipio) === 1) {
            $response = $this->http()
                ->get(self::LOCALIDADES_BASE.'/municipios/'.$codigoMunicipio);

            if (! $response->successful()) {
                throw new RuntimeException('Falha ao consultar o município no IBGE.');
            }

            return $response->json() ?? [];
        }

        if ($cidade === null || trim($cidade) === '' || $uf === null || trim($uf) === '') {
            throw new RuntimeException('Informe um código IBGE válido ou a combinação cidade + UF.');
        }

        $response = $this->http()
            ->get(self::LOCALIDADES_BASE.'/estados/'.Str::upper(trim($uf)).'/municipios');

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao listar municípios da UF informada no IBGE.');
        }

        $cidadeNormalizada = $this->normalizeText($cidade);

        foreach ($response->json() ?? [] as $municipio) {
            if (! is_array($municipio)) {
                continue;
            }

            if ($this->normalizeText((string) ($municipio['nome'] ?? '')) === $cidadeNormalizada) {
                return $municipio;
            }
        }

        throw new RuntimeException('Município não encontrado para a UF informada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchHistorico(string $codigoMunicipio): array
    {
        $response = $this->http()
            ->get(self::BIBLIOTECA_BASE, [
                'aspas' => 3,
                'codmun' => $codigoMunicipio,
                'tipoRetorno' => 'json',
            ]);

        if (! $response->successful()) {
            return [];
        }

        $json = $response->json() ?? [];

        return is_array($json[$codigoMunicipio] ?? null)
            ? $json[$codigoMunicipio]
            : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPanorama(string $panoramaUrl): array
    {
        $response = $this->http()->get($panoramaUrl);

        if (! $response->successful()) {
            return [];
        }

        $html = $response->body();
        if ($html === '') {
            return [];
        }

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//ul[contains(@class,'resultados-padrao')]/li");

        if ($nodes === false) {
            return [];
        }

        $items = [];

        foreach ($nodes as $node) {
            if (! $node instanceof \DOMNode) {
                continue;
            }

            $labelResult = $xpath->query(".//div[contains(@class,'ind-label')]/p", $node);
            $valueResult = $xpath->query(".//p[contains(@class,'ind-value')]", $node);
            $unitResult = $xpath->query(".//span[contains(@class,'indicador-unidade')]", $node);
            $periodResult = $xpath->query('.//small', $node);

            $labelNode = $labelResult !== false ? $labelResult->item(0) : null;
            $valueNode = $valueResult !== false ? $valueResult->item(0) : null;
            $unitNode = $unitResult !== false ? $unitResult->item(0) : null;
            $periodNode = $periodResult !== false ? $periodResult->item(0) : null;

            $label = $this->cleanText($labelNode instanceof \DOMNode ? $labelNode->textContent : null);
            $unit = $this->cleanText($unitNode instanceof \DOMNode ? $unitNode->textContent : null);
            $periodoTexto = $this->cleanText($periodNode instanceof \DOMNode ? $periodNode->textContent : null);
            $periodo = trim($periodoTexto, '[]');
            $valorBruto = $this->cleanText($valueNode instanceof \DOMNode ? $valueNode->textContent : null);

            if ($label === '' || $valorBruto === '') {
                continue;
            }

            $valor = trim(str_replace(
                array_filter([$unit, $periodoTexto]),
                '',
                $valorBruto
            ));

            $items[] = [
                'indicador' => $label,
                'valor' => $valor !== '' ? $valor : $valorBruto,
                'unidade' => $unit !== '' ? $unit : null,
                'periodo' => $periodo !== '' ? $periodo : null,
                'grafico_id' => $node instanceof \DOMElement ? ($node->getAttribute('data-grafico') ?: null) : null,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchIndicador(
        string $codigoMunicipio,
        int $pesquisaId,
        string $periodo,
        int $indicadorId
    ): ?array {
        $response = $this->http()->get(
            $this->buildIndicadorUrl($codigoMunicipio, $pesquisaId, $periodo, $indicadorId)
        );

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json() ?? [];
        $primeiro = is_array($json[0] ?? null) ? $json[0] : null;

        if ($primeiro === null) {
            return null;
        }

        $resultado = $primeiro['res'][0] ?? null;
        $serie = is_array($resultado['res'] ?? null) ? $resultado['res'] : null;

        if ($serie === null || $serie === []) {
            return null;
        }

        $ultimoPeriodo = array_key_last($serie);
        $valor = $serie[$ultimoPeriodo] ?? null;

        if ($valor === null || $valor === '-') {
            return null;
        }

        return [
            'indicador' => $primeiro['indicador'] ?? null,
            'valor' => $valor,
            'unidade' => $primeiro['unidade']['id'] ?? null,
            'periodo' => $ultimoPeriodo,
            'fonte' => $primeiro['fonte'][0]['fontes'][0] ?? null,
        ];
    }

    private function buildIndicadorUrl(
        string $codigoMunicipio,
        int $pesquisaId,
        string $periodo,
        int $indicadorId
    ): string {
        return self::PESQUISAS_BASE
            ."/{$pesquisaId}/periodos/{$periodo}/indicadores/{$indicadorId}"
            .'?scope=all&localidade='.$codigoMunicipio
            .'&lang=PT';
    }

    private function normalizeText(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();
    }

    private function cleanText(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode((string) $value)) ?? '');
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(20)
            ->retry(2, 300);
    }
}
