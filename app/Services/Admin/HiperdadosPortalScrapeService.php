<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Parsers\Hiperdados\PortalTerrenoClient;
use App\Services\Parsers\Hiperdados\PortalTerrenoCorretoresParser;
use App\Services\Parsers\Hiperdados\PortalTerrenoFichaParser;
use App\Services\Parsers\Hiperdados\PortalTerrenoFormularioParser;
use App\Services\Tenant\PortalTerrenoScraperService;
use RuntimeException;
use Throwable;

/**
 * Extrai e enriquece terrenos do portal comproterreno.com.br (Hiperdados).
 */
class HiperdadosPortalScrapeService
{
    public const BASE_URL = 'https://comproterreno.com.br/login';

    public function __construct(
        private readonly PortalTerrenoScraperService $scraper,
        private readonly PortalTerrenoFichaParser $fichaParser,
        private readonly PortalTerrenoFormularioParser $formularioParser,
        private readonly PortalTerrenoCorretoresParser $corretoresParser,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function extractList(string $username, string $password, ?int $limit = null): array
    {
        $client = $this->loginClient($username, $password);
        $mapHtml = $client->get(self::BASE_URL.'/Terrenos/Mapa', referer: self::BASE_URL.'/');
        $lista = $this->scraper->extractFromHtml($mapHtml);

        if ($limit !== null && $limit > 0) {
            $lista = array_slice($lista, 0, $limit);
        }

        if ($lista === []) {
            throw new RuntimeException('Nenhum terreno foi extraído do portal.');
        }

        return array_values($lista);
    }

    /**
     * Enriquece um lote de itens da lista básica.
     *
     * @param  list<array<string, mixed>>  $listaSlice
     * @return array{
     *     items: list<array<string, mixed>>,
     *     failures: list<array{id: mixed, erro: string}>
     * }
     */
    public function enrichBatch(string $username, string $password, array $listaSlice): array
    {
        $client = $this->loginClient($username, $password);
        $items = [];
        $failures = [];

        foreach ($listaSlice as $terreno) {
            $id = $terreno['id'] ?? null;

            if ($id === null || $id === '') {
                $failures[] = ['id' => null, 'erro' => 'Terreno sem id no mapa.'];
                $items[] = array_merge($terreno, [
                    'ficha' => null,
                    'formulario' => [],
                    'corretores' => [],
                ]);

                continue;
            }

            try {
                $items[] = $this->enrichOne($client, $terreno, (string) $id);
            } catch (Throwable $e) {
                $failures[] = ['id' => $id, 'erro' => $e->getMessage()];
                $items[] = array_merge($terreno, [
                    'ficha' => null,
                    'formulario' => [],
                    'corretores' => [],
                    'enrich_error' => $e->getMessage(),
                ]);
            }
        }

        return ['items' => $items, 'failures' => $failures];
    }

    /**
     * Fluxo completo síncrono (CLI / testes).
     *
     * @param  callable(int $processed, int $failed, int $total, array<string, mixed>|null $item): void|null  $onProgress
     * @return array{
     *     terrenos: list<array<string, mixed>>,
     *     total: int,
     *     failed: int,
     *     failures: list<array{id: mixed, erro: string}>
     * }
     */
    public function fetchAndEnrich(
        string $username,
        string $password,
        ?int $limit = null,
        ?callable $onProgress = null,
    ): array {
        $lista = $this->extractList($username, $password, $limit);
        $client = $this->loginClient($username, $password);
        $total = count($lista);
        $enriquecidos = [];
        $failures = [];

        foreach ($lista as $index => $terreno) {
            $id = $terreno['id'] ?? null;
            $processed = $index + 1;

            if ($id === null || $id === '') {
                $failures[] = ['id' => null, 'erro' => 'Terreno sem id no mapa.'];
                $enriquecidos[] = array_merge($terreno, [
                    'ficha' => null,
                    'formulario' => [],
                    'corretores' => [],
                ]);
                if ($onProgress !== null) {
                    $onProgress($processed, count($failures), $total, null);
                }

                continue;
            }

            try {
                $enriquecidos[] = $this->enrichOne($client, $terreno, (string) $id);
            } catch (Throwable $e) {
                $failures[] = ['id' => $id, 'erro' => $e->getMessage()];
                $enriquecidos[] = array_merge($terreno, [
                    'ficha' => null,
                    'formulario' => [],
                    'corretores' => [],
                    'enrich_error' => $e->getMessage(),
                ]);
            }

            if ($onProgress !== null) {
                $onProgress(
                    $processed,
                    count($failures),
                    $total,
                    $enriquecidos[array_key_last($enriquecidos)] ?? null,
                );
            }
        }

        return [
            'terrenos' => $enriquecidos,
            'total' => count($enriquecidos),
            'failed' => count($failures),
            'failures' => $failures,
        ];
    }

    private function loginClient(string $username, string $password): PortalTerrenoClient
    {
        $client = new PortalTerrenoClient(self::BASE_URL.'/');
        $client->login($username, $password);

        return $client;
    }

    /**
     * @param  array<string, mixed>  $terreno
     * @return array<string, mixed>
     */
    private function enrichOne(PortalTerrenoClient $client, array $terreno, string $id): array
    {
        $fichaHtml = $client->get(
            self::BASE_URL."/Terrenos/Visualizar/{$id}",
            referer: self::BASE_URL.'/Terrenos/Mapa',
        );
        $ficha = $this->fichaParser->parse($fichaHtml);

        $formularioResp = $client->postForm(
            self::BASE_URL.'/terrenos_terrenos_formulario',
            ['TER_ID' => base64_encode($id), 'visualizar' => '1'],
            referer: self::BASE_URL."/Terrenos/Visualizar/{$id}",
        );
        $formularioData = json_decode($formularioResp, true);
        $formulario = is_array($formularioData) && ($formularioData['sucesso'] ?? '') === 'true'
            ? $this->formularioParser->parse((string) ($formularioData['strHtml'] ?? ''))
            : [];

        $corretoresResp = $client->postForm(
            self::BASE_URL.'/terrenos_corretores_consultar',
            ['TER_ID' => base64_encode($id), 'visualizar' => '1'],
            referer: self::BASE_URL."/Terrenos/Visualizar/{$id}",
        );
        $corretoresData = json_decode($corretoresResp, true);
        $corretores = is_array($corretoresData) && ($corretoresData['sucesso'] ?? '') === 'true'
            ? $this->corretoresParser->parse((string) ($corretoresData['strHtml'] ?? ''))
            : [];

        return array_merge($terreno, [
            'ficha' => $ficha,
            'formulario' => $formulario,
            'corretores' => $corretores,
        ]);
    }
}
