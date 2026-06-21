<?php

namespace App\Services\Ai\Tools;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiMercadoImobiliarioService
{
    private const CACHE_TTL = 60 * 60 * 24; // 24 horas

    // OLX (mesmo grupo que ZAP/VivaReal — dados de lançamentos via __NEXT_DATA__)
    private const OLX_URL = 'https://www.olx.com.br/imoveis/lancamentos';

    private const SERPER_SEARCH = 'https://google.serper.dev/search';

    private const SERPER_NEWS = 'https://google.serper.dev/news';

    // Páginas a percorrer por busca (cada página = ~10 resultados, 1 crédito Serper)
    private const SERPER_MAX_PAGINAS = 5;

    /**
     * Pesquisa empreendimentos imobiliários consultando OLX e busca web.
     * Resultados são cacheados por 24h por cidade+estado+anos.
     *
     * @return array<string, mixed>
     */
    public function pesquisar(string $cidade, ?string $estado = null, int $anos = 5): array
    {
        $cacheKey = sprintf(
            'ai:mercado-imobiliario:%s:%s:%d',
            Str::slug($cidade),
            Str::lower($estado ?? 'br'),
            $anos
        );

        return Cache::remember($cacheKey, self::CACHE_TTL, fn (): array => $this->executarPesquisa($cidade, $estado, $anos));
    }

    // ── Orquestrador ──────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function executarPesquisa(string $cidade, ?string $estado, int $anos): array
    {
        $empreendimentos = [];
        $fontesConsultadas = [];
        $erros = [];

        // Fonte 1: OLX lançamentos — portal do grupo ZAP/VivaReal, dados estruturados
        try {
            $items = $this->buscarOlx($cidade, $estado);
            $empreendimentos = array_merge($empreendimentos, $items);
            if ($items !== []) {
                $fontesConsultadas[] = 'OLX/ZAP Lançamentos';
            }
        } catch (\Throwable $e) {
            $erros[] = 'OLX: '.$e->getMessage();
            Log::warning('AiMercadoImobiliario: OLX falhou', ['error' => $e->getMessage()]);
        }

        // Fontes 2, 3 e 4: Serper (web + construtoras + news) — somente se chave configurada
        if (config('services.serper.key')) {
            // Busca geral por empreendimentos e lançamentos
            try {
                $items = $this->buscarSerperWeb($cidade, $estado, $anos);
                $empreendimentos = array_merge($empreendimentos, $items);
                if ($items !== []) {
                    $fontesConsultadas[] = 'Busca Web (Google)';
                }
            } catch (\Throwable $e) {
                $erros[] = 'Busca Web: '.$e->getMessage();
                Log::warning('AiMercadoImobiliario: Serper web falhou', ['error' => $e->getMessage()]);
            }

            // Busca específica por construtoras e incorporadoras ativas na cidade
            try {
                $items = $this->buscarSerperConstrutoras($cidade, $estado);
                $empreendimentos = array_merge($empreendimentos, $items);
                if ($items !== []) {
                    $fontesConsultadas[] = 'Construtoras (Google)';
                }
            } catch (\Throwable $e) {
                $erros[] = 'Construtoras: '.$e->getMessage();
                Log::warning('AiMercadoImobiliario: Serper construtoras falhou', ['error' => $e->getMessage()]);
            }

            // Busca por segmento econômico/popular (MCMV) — construtoras como Pacaembu, MRV, Tenda
            try {
                $items = $this->buscarSerperSegmentoPopular($cidade, $estado);
                $empreendimentos = array_merge($empreendimentos, $items);
                if ($items !== []) {
                    $fontesConsultadas[] = 'Segmento Popular/MCMV (Google)';
                }
            } catch (\Throwable $e) {
                $erros[] = 'Segmento Popular: '.$e->getMessage();
                Log::warning('AiMercadoImobiliario: Serper popular falhou', ['error' => $e->getMessage()]);
            }

            // Notícias recentes sobre lançamentos na cidade
            try {
                $items = $this->buscarSerperNews($cidade, $estado, $anos);
                $empreendimentos = array_merge($empreendimentos, $items);
                if ($items !== []) {
                    $fontesConsultadas[] = 'Notícias de Lançamentos';
                }
            } catch (\Throwable $e) {
                $erros[] = 'Notícias: '.$e->getMessage();
                Log::warning('AiMercadoImobiliario: Serper news falhou', ['error' => $e->getMessage()]);
            }
        }

        $empreendimentos = $this->deduplicar($empreendimentos);

        return [
            'cidade_pesquisada' => $cidade,
            'estado' => $estado,
            'periodo_anos' => $anos,
            'total_encontrados' => count($empreendimentos),
            'fontes_consultadas' => $fontesConsultadas,
            'erros_de_consulta' => $erros ?: null,
            'aviso_cobertura' => $this->gerarAvisoCobertura((bool) config('services.serper.key'), $empreendimentos),
            'empreendimentos' => $empreendimentos,
        ];
    }

    // ── Fonte 1: OLX (__NEXT_DATA__ scraping) ────────────────────────

    /**
     * Extrai lançamentos do OLX via __NEXT_DATA__ (JSON embutido na página).
     * OLX pertence ao mesmo grupo que ZAP Imóveis e VivaReal.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buscarOlx(string $cidade, ?string $estado): array
    {
        $params = ['q' => $cidade];
        if ($estado) {
            $params['uf'] = Str::lower($estado);
        }

        $response = $this->httpNavegador()
            ->get(self::OLX_URL, $params);

        if (! $response->successful()) {
            return [];
        }

        if (! preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/si', $response->body(), $m)) {
            return [];
        }

        /** @var array<string, mixed> $nextData */
        $nextData = json_decode($m[1], true) ?? [];
        /** @var array<int, array<string, mixed>> $ads */
        $ads = data_get($nextData, 'props.pageProps.ads', []);

        if ($ads === []) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(fn (array $ad) => $this->normalizarOlx($ad), $ads),
                fn (?array $item) => $item !== null
            )
        );
    }

    // ── Fonte 2: Serper — busca web geral ─────────────────────────────

    /**
     * Busca ampla por empreendimentos, lançamentos e construtoras.
     * Sem aspas exatas (que sufocam o Google) e com paginação para
     * captar o máximo de resultados orgânicos.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buscarSerperWeb(string $cidade, ?string $estado, int $anos): array
    {
        $localidade = $estado ? "{$cidade} {$estado}" : $cidade;
        $query = "lançamentos apartamentos casas empreendimentos {$localidade} construtora incorporadora";

        return $this->serperSearch($query, $cidade, $estado, 'Busca Web');
    }

    // ── Fonte 3: Serper — notícias ────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buscarSerperNews(string $cidade, ?string $estado, int $anos): array
    {
        $localidade = $estado ? "{$cidade} {$estado}" : $cidade;
        $query = "lançamento imobiliário novo empreendimento {$localidade}";

        $itens = [];

        for ($page = 1; $page <= self::SERPER_MAX_PAGINAS; $page++) {
            $response = $this->serperHttp()->post(self::SERPER_NEWS, [
                'q' => $query,
                'gl' => 'br',
                'hl' => 'pt-br',
                'page' => $page,
                'tbs' => "cdr:1,cd_min:01/01/{$this->anoMinimo($anos)},cd_max:31/12/".now()->year,
            ]);

            if (! $response->successful()) {
                break;
            }

            $news = (array) data_get($response->json(), 'news', []);
            if ($news === []) {
                break;
            }

            foreach ($news as $item) {
                $itens[] = $this->normalizarSerper((array) $item, $cidade, $estado, 'Notícias');
            }
        }

        return $itens;
    }

    // ── Fonte extra: Serper construtoras ─────────────────────────────

    /**
     * Busca construtoras e incorporadoras ativas na cidade via Google.
     * Cobre empresas que vendem pelo site próprio e não aparecem em portais.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buscarSerperConstrutoras(string $cidade, ?string $estado): array
    {
        $localidade = $estado ? "{$cidade} {$estado}" : $cidade;
        $query = "construtora incorporadora loteadora loteamento condominio empreendimento residencial {$localidade}";

        return $this->serperSearch($query, $cidade, $estado, 'Site de Construtora');
    }

    // ── Fonte extra: Serper segmento popular/MCMV ────────────────────

    /**
     * Busca empreendimentos do segmento popular e MCMV.
     * Construtoras populares (Pacaembu, MRV, Tenda, Cury) raramente
     * listam nos portais — vendem via MCMV/CEF e site próprio.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buscarSerperSegmentoPopular(string $cidade, ?string $estado): array
    {
        $localidade = $estado ? "{$cidade} {$estado}" : $cidade;
        $query = "Minha Casa Minha Vida MCMV casas apartamentos {$localidade} construtora";

        return $this->serperSearch($query, $cidade, $estado, 'Segmento Popular/MCMV');
    }

    // ── Helper: busca paginada no endpoint /search ───────────────────

    /**
     * Executa uma busca no Serper paginando até SERPER_MAX_PAGINAS,
     * retornando TODOS os resultados orgânicos encontrados.
     *
     * @return array<int, array<string, mixed>>
     */
    private function serperSearch(string $query, string $cidade, ?string $estado, string $fonte): array
    {
        $itens = [];

        for ($page = 1; $page <= self::SERPER_MAX_PAGINAS; $page++) {
            $response = $this->serperHttp()->post(self::SERPER_SEARCH, [
                'q' => $query,
                'gl' => 'br',
                'hl' => 'pt-br',
                'page' => $page,
            ]);

            if (! $response->successful()) {
                break;
            }

            $organic = (array) data_get($response->json(), 'organic', []);
            if ($organic === []) {
                break;
            }

            foreach ($organic as $item) {
                $itens[] = $this->normalizarSerper((array) $item, $cidade, $estado, $fonte);
            }
        }

        return $itens;
    }

    private function serperHttp(): PendingRequest
    {
        return Http::withHeaders([
            'X-API-KEY' => (string) config('services.serper.key'),
            'Content-Type' => 'application/json',
        ])->timeout(15);
    }

    // ── Aviso de cobertura ────────────────────────────────────────────

    /**
     * Gera aviso explícito para o agente sobre lacunas de cobertura.
     * O agente deve comunicar essas limitações proativamente ao usuário.
     *
     * @param array<int, array<string, mixed>> $empreendimentos
     */
    private function gerarAvisoCobertura(bool $serperAtivo, array $empreendimentos): string
    {
        $avisos = [];

        if (! $serperAtivo) {
            $avisos[] = 'SERPER_API_KEY não configurada: cobertura limitada ao OLX/ZAP. '
                .'Construtoras que vendem pelo site próprio (Pacaembu, MRV, Tenda, Cury, Tegra, etc.) '
                .'não são detectadas sem a busca web.';
        }

        $temPopular = collect($empreendimentos)->contains(
            fn (array $e) => Str::contains(strtolower($e['fonte'] ?? ''), ['popular', 'mcmv'])
        );

        if (! $temPopular) {
            $avisos[] = 'IMPORTANTE: O segmento econômico/popular (MCMV) pode estar subrepresentado. '
                .'Grandes construtoras populares como Pacaembu, MRV, Tenda e Cury atuam via CEF/MCMV '
                .'e não listam empreendimentos em portais como OLX. '
                .'Recomende ao usuário verificar diretamente os sites dessas construtoras '
                .'e a lista de empreendimentos habilitados na CEF para a cidade.';
        }

        if ($empreendimentos === []) {
            $avisos[] = 'Nenhum empreendimento encontrado nas fontes consultadas. '
                .'Isso não significa ausência de mercado — pode indicar que as construtoras ativas '
                .'na cidade operam fora dos canais pesquisados. '
                .'Informe o usuário e sugira busca manual em ADEMI, SINDUSCON ou CEF local.';
        }

        return implode(' | ', $avisos) ?: 'Cobertura incluiu portais e busca web. '
            .'Construtoras sem presença digital podem não ter sido detectadas.';
    }

    // ── Normalizadores ────────────────────────────────────────────────

    /**
     * Normaliza um anúncio do OLX para o formato canônico.
     *
     * @param array<string, mixed> $ad
     * @return array<string, mixed>|null
     */
    private function normalizarOlx(array $ad): ?array
    {
        $subject = trim((string) ($ad['subject'] ?? ''));
        if ($subject === '') {
            return null;
        }

        // Indexar properties por name para acesso direto
        $props = [];
        foreach ((array) ($ad['properties'] ?? []) as $p) {
            if (is_array($p) && isset($p['name'])) {
                $props[(string) $p['name']] = (string) ($p['value'] ?? '');
            }
        }

        $locDetails = (array) ($ad['locationDetails'] ?? []);

        return [
            'nome' => $subject,
            'padrao' => $this->inferirPadrao(
                $this->extrairPreco((string) ($ad['priceValue'] ?? '')),
                $this->extrairAreaMedia($props['size'] ?? '')
            ),
            'tipologia' => $this->normalizarTipologia($props['rooms'] ?? ''),
            'area_util' => $this->normalizarArea($props['size'] ?? ''),
            'faixa_preco' => $this->normalizarPrecoOlx((string) ($ad['priceValue'] ?? '')),
            'construtora' => $props['property_developer_name'] ?: ($props['builder_name'] ?: null),
            'localizacao' => [
                'bairro' => $locDetails['neighbourhood'] ?? null,
                'cidade' => $locDetails['municipality'] ?? null,
                'estado' => $locDetails['uf'] ?? null,
            ],
            'data_lancamento' => isset($ad['date'])
                ? date('Y-m', (int) $ad['date']) : null,
            'status' => $this->normalizarStatusOlx($props['re_construction_status'] ?? $props['re_types'] ?? ''),
            'total_unidades' => null,
            'diferenciais' => ($props['re_complex_features'] ?? '') !== ''
                ? array_map('trim', explode(',', $props['re_complex_features']))
                : [],
            'fonte' => 'OLX/ZAP',
            'link' => $ad['url'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizarSerper(array $item, string $cidade, ?string $estado, string $fonte): array
    {
        return [
            'nome' => $item['title'] ?? null,
            'padrao' => null,
            'tipologia' => [],
            'area_util' => null,
            'faixa_preco' => null,
            'construtora' => null,
            'localizacao' => ['cidade' => $cidade, 'estado' => $estado],
            'data_lancamento' => $item['date'] ?? null,
            'status' => null,
            'total_unidades' => null,
            'diferenciais' => [],
            'descricao' => $item['snippet'] ?? null,
            'fonte' => $fonte,
            'link' => $item['link'] ?? null,
        ];
    }

    // ── Helpers de normalização ───────────────────────────────────────

    /**
     * @return array<int, string>
     */
    private function normalizarTipologia(string $rooms): array
    {
        if ($rooms === '') {
            return [];
        }

        // "2-4" → ["2 quartos", "3 quartos", "4 quartos"]
        if (preg_match('/^(\d+)-(\d+)$/', $rooms, $m)) {
            return array_map(fn (int $n) => "{$n} quartos", range((int) $m[1], (int) $m[2]));
        }

        return [(int) $rooms . ' quartos'];
    }

    /**
     * @return array<string, float|null>|null
     */
    private function normalizarArea(string $size): ?array
    {
        if ($size === '') {
            return null;
        }

        // "129m²-174m²" ou "108m²-112m²"
        preg_match_all('/(\d+(?:[.,]\d+)?)\s*m²/u', $size, $m);
        $valores = array_map('floatval', $m[1]);

        if ($valores === []) {
            return null;
        }

        return [
            'min' => min($valores),
            'max' => max($valores),
            'media' => round(array_sum($valores) / count($valores), 1),
        ];
    }

    /**
     * @return array<string, int|null|string>|null
     */
    private function normalizarPrecoOlx(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }

        $min = $this->extrairPreco($raw);
        if ($min <= 0) {
            return null;
        }

        // "A partir de R$ 1.250.000" → apenas mínimo
        return ['min' => $min, 'max' => null, 'moeda' => 'BRL'];
    }

    private function extrairPreco(string $raw): int
    {
        // Remove "A partir de", "R$", pontos de milhar
        preg_match('/[\d.,]+/', str_replace('.', '', $raw), $m);

        return (int) str_replace(',', '.', $m[0] ?? '0');
    }

    private function extrairAreaMedia(string $size): float
    {
        $area = $this->normalizarArea($size);

        return (float) ($area['media'] ?? 0);
    }

    private function normalizarStatusOlx(string $raw): ?string
    {
        return match (true) {
            Str::contains($raw, ['Na planta', 'Lançamento', 'planta']) => 'Lançamento',
            Str::contains($raw, ['Em obras', 'Em construção', 'construção']) => 'Em construção',
            Str::contains($raw, ['Pronto', 'pronto', 'Morar']) => 'Pronto',
            default => $raw !== '' ? $raw : null,
        };
    }

    private function inferirPadrao(int $precoMin, float $areaMedia): string
    {
        if ($precoMin <= 0 || $areaMedia <= 0) {
            return 'Não identificado';
        }

        $precoM2 = $precoMin / $areaMedia;

        return match (true) {
            $precoM2 >= 15000 => 'Luxo',
            $precoM2 >= 10000 => 'Alto Padrão',
            $precoM2 >= 6000 => 'Médio-Alto',
            $precoM2 >= 3500 => 'Médio',
            default => 'Popular',
        };
    }

    /**
     * @param array<int, array<string, mixed>> $empreendimentos
     * @return array<int, array<string, mixed>>
     */
    private function deduplicar(array $empreendimentos): array
    {
        $seen = [];
        $resultado = [];

        foreach ($empreendimentos as $emp) {
            $chave = Str::lower(trim((string) ($emp['nome'] ?? '')));
            if ($chave === '' || isset($seen[$chave])) {
                continue;
            }
            $seen[$chave] = true;
            $resultado[] = $emp;
        }

        return $resultado;
    }

    private function anoMinimo(int $anos): int
    {
        return now()->year - $anos;
    }

    private function httpNavegador(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'pt-BR,pt;q=0.9',
            'Accept-Encoding' => 'identity',
        ])
            ->timeout(15)
            ->retry(1, 800);
    }
}
