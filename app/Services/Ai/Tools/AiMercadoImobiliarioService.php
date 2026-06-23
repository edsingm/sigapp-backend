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

    private const OLX_URL = 'https://www.olx.com.br/imoveis/lancamentos';
    private const SERPER_SEARCH = 'https://google.serper.dev/search';
    private const SERPER_NEWS = 'https://google.serper.dev/news';
    private const SERPER_MAX_PAGINAS = 5;

    /**
     * Pesquisa empreendimentos imobiliários.
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

    // ==================== ORQUESTRADOR ====================

    private function executarPesquisa(string $cidade, ?string $estado, int $anos): array
    {
        $empreendimentos = [];
        $fontesConsultadas = [];
        $erros = [];

        // Fonte 1: OLX
        try {
            $items = $this->buscarOlx($cidade, $estado);
            $empreendimentos = array_merge($empreendimentos, $items);
            if (!empty($items)) {
                $fontesConsultadas[] = 'OLX/ZAP Lançamentos';
            }
        } catch (\Throwable $e) {
            $erros[] = 'OLX: ' . $e->getMessage();
            Log::warning('AiMercadoImobiliario: OLX falhou', ['error' => $e->getMessage()]);
        }

        // Fontes Serper
        if (config('services.serper.key')) {
            // Busca Web Geral
            try {
                $items = $this->buscarSerperWeb($cidade, $estado, $anos);
                $items = $this->filtrarResultadosRelevantes($items, $cidade, $estado);
                $empreendimentos = array_merge($empreendimentos, $items);
                if (!empty($items)) $fontesConsultadas[] = 'Busca Web (Google)';
            } catch (\Throwable $e) {
                $erros[] = 'Busca Web: ' . $e->getMessage();
            }

            // Construtoras
            try {
                $items = $this->buscarSerperConstrutoras($cidade, $estado);
                $items = $this->filtrarResultadosRelevantes($items, $cidade, $estado);
                $empreendimentos = array_merge($empreendimentos, $items);
                if (!empty($items)) $fontesConsultadas[] = 'Construtoras (Google)';
            } catch (\Throwable $e) {
                $erros[] = 'Construtoras: ' . $e->getMessage();
            }

            // Segmento Popular / MCMV
            try {
                $items = $this->buscarSerperSegmentoPopular($cidade, $estado);
                $items = $this->filtrarResultadosRelevantes($items, $cidade, $estado);
                $empreendimentos = array_merge($empreendimentos, $items);
                if (!empty($items)) $fontesConsultadas[] = 'Segmento Popular/MCMV (Google)';
            } catch (\Throwable $e) {
                $erros[] = 'Segmento Popular: ' . $e->getMessage();
            }

            // Notícias
            try {
                $items = $this->buscarSerperNews($cidade, $estado, $anos);
                $items = $this->filtrarResultadosRelevantes($items, $cidade, $estado);
                $empreendimentos = array_merge($empreendimentos, $items);
                if (!empty($items)) $fontesConsultadas[] = 'Notícias de Lançamentos';
            } catch (\Throwable $e) {
                $erros[] = 'Notícias: ' . $e->getMessage();
            }
        }

        $empreendimentos = $this->deduplicar($empreendimentos);

        return [
            'cidade_pesquisada'  => $cidade,
            'estado'             => $estado,
            'periodo_anos'       => $anos,
            'total_encontrados'  => count($empreendimentos),
            'fontes_consultadas' => $fontesConsultadas,
            'erros_de_consulta'  => $erros ?: null,
            'aviso_cobertura'    => $this->gerarAvisoCobertura((bool) config('services.serper.key'), $empreendimentos),
            'empreendimentos'    => $empreendimentos,
        ];
    }

    // ==================== BUSCAS SERPER ====================

    private function buscarSerperWeb(string $cidade, ?string $estado, int $anos): array
    {
        $localidade = $estado ? "{$cidade} {$estado}" : $cidade;
        $query = sprintf(
            '"lançamento" OR "lançamentos" OR "em breve" %s (apartamento OR casa OR condomínio OR empreendimento)',
            $localidade
        );

        return $this->serperSearch($query, $cidade, $estado, 'Busca Web', $anos);
    }

    private function buscarSerperConstrutoras(string $cidade, ?string $estado): array
    {
        $localidade = $estado ? "{$cidade} {$estado}" : $cidade;
        $query = "construtora OR incorporadora {$localidade} (lançamento OR empreendimento OR condomínio)";

        return $this->serperSearch($query, $cidade, $estado, 'Site de Construtora');
    }

    private function buscarSerperSegmentoPopular(string $cidade, ?string $estado): array
    {
        $localidade = $estado ? "{$cidade} {$estado}" : $cidade;
        $query = "(MCMV OR \"Minha Casa Minha Vida\" OR Pacaembu OR MRV OR Tenda OR Cury) {$localidade}";

        return $this->serperSearch($query, $cidade, $estado, 'Segmento Popular/MCMV');
    }

    private function buscarSerperNews(string $cidade, ?string $estado, int $anos): array
    {
        $localidade = $estado ? "{$cidade} {$estado}" : $cidade;
        $query = "lançamento imobiliário novo empreendimento {$localidade}";

        $itens = [];

        for ($page = 1; $page <= self::SERPER_MAX_PAGINAS; $page++) {
            $response = $this->serperHttp()->post(self::SERPER_NEWS, [
                'q'    => $query,
                'gl'   => 'br',
                'hl'   => 'pt-br',
                'page' => $page,
                'tbs'  => "cdr:1,cd_min:01/01/{$this->anoMinimo($anos)},cd_max:31/12/" . now()->year,
            ]);

            if (!$response->successful()) break;

            $news = (array) data_get($response->json(), 'news', []);
            if (empty($news)) break;

            foreach ($news as $item) {
                $itens[] = $this->normalizarSerper((array) $item, $cidade, $estado, 'Notícias');
            }
        }

        return $itens;
    }

    private function serperSearch(string $query, string $cidade, ?string $estado, string $fonte, ?int $anos = null): array
    {
        $itens = [];

        $params = [
            'q'   => $query,
            'gl'  => 'br',
            'hl'  => 'pt-br',
        ];

        if ($anos !== null) {
            $params['tbs'] = sprintf(
                "cdr:1,cd_min:01/01/%d,cd_max:31/12/%d",
                $this->anoMinimo($anos),
                now()->year
            );
        }

        for ($page = 1; $page <= self::SERPER_MAX_PAGINAS; $page++) {
            $params['page'] = $page;

            $response = $this->serperHttp()->post(self::SERPER_SEARCH, $params);

            if (!$response->successful()) {
                break;
            }

            $organic = (array) data_get($response->json(), 'organic', []);
            if (empty($organic)) {
                break;
            }

            foreach ($organic as $item) {
                $itens[] = $this->normalizarSerper((array)$item, $cidade, $estado, $fonte);
            }
        }

        return $itens;
    }

    // ==================== FILTRO DE RELEVÂNCIA ====================

    private function filtrarResultadosRelevantes(array $itens, string $cidade, ?string $estado): array
    {
        $cidadeLower = Str::lower($cidade);
        $palavrasChave = ['lançamento', 'lançamentos', 'breve', 'em breve', 'nova torre', 'fase', 'condomínio', 'residencial'];

        return array_values(array_filter($itens, function (array $item) use ($cidadeLower, $palavrasChave) {
            $texto = Str::lower(($item['nome'] ?? '') . ' ' . ($item['descricao'] ?? ''));

            if (!Str::contains($texto, $cidadeLower)) {
                return false;
            }

            return Str::contains($texto, $palavrasChave);
        }));
    }

    // ==================== NORMALIZAÇÃO SERPER ====================

    private function normalizarSerper(array $item, string $cidade, ?string $estado, string $fonte): array
    {
        $title = trim((string)($item['title'] ?? ''));
        $snippet = trim((string)($item['snippet'] ?? ''));
        $texto = $title . ' ' . $snippet;

        return [
            'nome'            => $title ?: null,
            'padrao'          => $this->inferirPadraoDoTexto($texto),
            'tipologia'       => $this->extrairTipologiaDoTexto($texto),
            'area_util'       => $this->extrairAreaDoTexto($texto),
            'faixa_preco'     => $this->extrairPrecoDoTexto($texto),
            'construtora'     => $this->extrairConstrutoraDoTexto($texto),
            'localizacao'     => ['cidade' => $cidade, 'estado' => $estado],
            'data_lancamento' => $item['date'] ?? null,
            'status'          => $this->extrairStatusDoTexto($texto),
            'total_unidades'  => null,
            'diferenciais'    => [],
            'descricao'       => $snippet ?: null,
            'fonte'           => $fonte,
            'link'            => $item['link'] ?? null,
            'score'           => $this->calcularScoreSerper($item, $fonte),
        ];
    }

    // ==================== MÉTODOS DE EXTRAÇÃO ====================

    private function inferirPadraoDoTexto(string $texto): string
    {
        $t = Str::lower($texto);

        if (Str::contains($t, ['luxo', 'alto padrão', 'premium', 'cobertura de luxo'])) return 'Luxo';
        if (Str::contains($t, ['mcmv', 'minha casa', 'econômico', 'popular', 'subsídio'])) return 'Popular';
        if (Str::contains($t, ['alto padrão', 'médio-alto'])) return 'Alto Padrão';
        if (Str::contains($t, ['médio'])) return 'Médio';

        return 'Não identificado';
    }

    private function extrairTipologiaDoTexto(string $texto): array
    {
        preg_match_all('/(\d+)\s*(?:quartos?|qtos?)/i', $texto, $matches);
        $tipos = array_unique($matches[1] ?? []);
        return array_map(fn($q) => $q . ' quartos', $tipos);
    }

    private function extrairAreaDoTexto(string $texto): ?array
    {
        preg_match_all('/(\d+(?:[.,]\d+)?)\s*m²/i', $texto, $matches);
        $valores = array_map('floatval', $matches[1] ?? []);

        if (empty($valores)) return null;

        return [
            'min'   => min($valores),
            'max'   => max($valores),
            'media' => round(array_sum($valores) / count($valores), 1),
        ];
    }

    private function extrairPrecoDoTexto(string $texto): ?array
    {
        if (!preg_match('/R\$\s*([\d.,]+)/i', $texto, $match)) {
            return null;
        }

        $valor = (int)str_replace(['.', ','], ['', '.'], $match[1]);

        return $valor > 0 ? ['min' => $valor, 'max' => null, 'moeda' => 'BRL'] : null;
    }

    private function extrairConstrutoraDoTexto(string $texto): ?string
    {
        $conhecidas = ['MRV', 'Tenda', 'Cury', 'Pacaembu', 'Cyrela', 'Tegra', 'EZTec', 'JHSF', 'Gafisa', 'Brookfield'];

        foreach ($conhecidas as $nome) {
            if (Str::contains($texto, $nome)) {
                return $nome;
            }
        }

        if (preg_match('/(?:da construtora|da incorporadora)\s+([A-Za-z][A-Za-z\s]+)/i', $texto, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    private function extrairStatusDoTexto(string $texto): ?string
    {
        $t = Str::lower($texto);

        return match (true) {
            Str::contains($t, ['lançamento', 'breve', 'em breve']) => 'Lançamento',
            Str::contains($t, ['em obras', 'construção', 'andamento']) => 'Em construção',
            Str::contains($t, ['pronto', 'entrega imediata', 'morar']) => 'Pronto',
            default => null,
        };
    }

    private function calcularScoreSerper(array $item, string $fonte): int
    {
        $score = 45;

        if (!empty($item['date'])) $score += 15;
        if (Str::contains($fonte, 'Construtora')) $score += 20;
        if (Str::contains($fonte, 'Popular')) $score += 10;

        $texto = Str::lower(($item['title'] ?? '') . ' ' . ($item['snippet'] ?? ''));

        if (Str::contains($texto, ['lançamento', 'breve'])) $score += 15;
        if (Str::contains($texto, ['mcmv', 'minha casa'])) $score += 10;

        return min($score, 100);
    }

    // ==================== DEDUPLICAÇÃO ====================

    private function deduplicar(array $empreendimentos): array
    {
        $seen = [];
        $resultado = [];

        foreach ($empreendimentos as $emp) {
            $chave = $this->normalizarNomeParaComparacao($emp['nome'] ?? '');
            if ($chave === '' || isset($seen[$chave])) {
                continue;
            }
            $seen[$chave] = true;
            $resultado[] = $emp;
        }

        return $resultado;
    }

    private function normalizarNomeParaComparacao(?string $nome): string
    {
        if (!$nome) return '';

        $nome = Str::lower(trim($nome));
        $nome = Str::ascii($nome);

        $remover = ['residencial', 'condomínio', 'condominio', 'loteamento', 'empreendimento', 'torre', 'fase', 'inc', 'ltda'];
        $nome = preg_replace('/\b(' . implode('|', $remover) . ')\b/u', '', $nome);

        return trim(preg_replace('/\s+/', ' ', $nome));
    }

    // ==================== OLX ====================

    private function buscarOlx(string $cidade, ?string $estado): array
    {
        $params = ['q' => $cidade];
        if ($estado) {
            $params['uf'] = Str::lower($estado);
        }

        $response = $this->httpNavegador()->get(self::OLX_URL, $params);

        if (!$response->successful()) {
            return [];
        }

        if (!preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/si', $response->body(), $m)) {
            return [];
        }

        $nextData = json_decode($m[1], true) ?? [];
        $ads = data_get($nextData, 'props.pageProps.ads', []);

        if (empty($ads)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn(array $ad) => $this->normalizarOlx($ad), $ads),
            fn(?array $item) => $item !== null
        ));
    }

    private function normalizarOlx(array $ad): ?array
    {
        $subject = trim((string)($ad['subject'] ?? ''));
        if ($subject === '') return null;

        $props = [];
        foreach ((array)($ad['properties'] ?? []) as $p) {
            if (is_array($p) && isset($p['name'])) {
                $props[(string)$p['name']] = (string)($p['value'] ?? '');
            }
        }

        $locDetails = (array)($ad['locationDetails'] ?? []);

        return [
            'nome'            => $subject,
            'padrao'          => $this->inferirPadrao(
                $this->extrairPreco((string)($ad['priceValue'] ?? '')),
                $this->extrairAreaMedia($props['size'] ?? '')
            ),
            'tipologia'       => $this->normalizarTipologia($props['rooms'] ?? ''),
            'area_util'       => $this->normalizarArea($props['size'] ?? ''),
            'faixa_preco'     => $this->normalizarPrecoOlx((string)($ad['priceValue'] ?? '')),
            'construtora'     => $props['property_developer_name'] ?: ($props['builder_name'] ?: null),
            'localizacao'     => [
                'bairro'  => $locDetails['neighbourhood'] ?? null,
                'cidade'  => $locDetails['municipality'] ?? null,
                'estado'  => $locDetails['uf'] ?? null,
            ],
            'data_lancamento' => isset($ad['date']) ? date('Y-m', (int)$ad['date']) : null,
            'status'          => $this->normalizarStatusOlx($props['re_construction_status'] ?? $props['re_types'] ?? ''),
            'total_unidades'  => null,
            'diferenciais'    => ($props['re_complex_features'] ?? '') !== ''
                ? array_map('trim', explode(',', $props['re_complex_features']))
                : [],
            'fonte'           => 'OLX/ZAP',
            'link'            => $ad['url'] ?? null,
            'score'           => 85,
        ];
    }

    // ==================== HELPERS DE NORMALIZAÇÃO (OLX) ====================

    private function normalizarTipologia(string $rooms): array
    {
        if ($rooms === '') return [];

        if (preg_match('/^(\d+)-(\d+)$/', $rooms, $m)) {
            return array_map(fn(int $n) => "{$n} quartos", range((int)$m[1], (int)$m[2]));
        }

        return [(int)$rooms . ' quartos'];
    }

    private function normalizarArea(string $size): ?array
    {
        if ($size === '') return null;

        preg_match_all('/(\d+(?:[.,]\d+)?)\s*m²/u', $size, $m);
        $valores = array_map('floatval', $m[1]);

        if (empty($valores)) return null;

        return [
            'min'   => min($valores),
            'max'   => max($valores),
            'media' => round(array_sum($valores) / count($valores), 1),
        ];
    }

    private function normalizarPrecoOlx(string $raw): ?array
    {
        if ($raw === '') return null;

        $min = $this->extrairPreco($raw);
        if ($min <= 0) return null;

        return ['min' => $min, 'max' => null, 'moeda' => 'BRL'];
    }

    private function extrairPreco(string $raw): int
    {
        preg_match('/[\d.,]+/', str_replace('.', '', $raw), $m);
        return (int)str_replace(',', '.', $m[0] ?? '0');
    }

    private function extrairAreaMedia(string $size): float
    {
        $area = $this->normalizarArea($size);
        return (float)($area['media'] ?? 0);
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
        if ($precoMin <= 0 || $areaMedia <= 0) return 'Não identificado';

        $precoM2 = $precoMin / $areaMedia;

        return match (true) {
            $precoM2 >= 15000 => 'Luxo',
            $precoM2 >= 10000 => 'Alto Padrão',
            $precoM2 >= 6000  => 'Médio-Alto',
            $precoM2 >= 3500  => 'Médio',
            default           => 'Popular',
        };
    }

    // ==================== OUTROS MÉTODOS ====================

    private function gerarAvisoCobertura(bool $serperAtivo, array $empreendimentos): string
    {
        $avisos = [];

        if (!$serperAtivo) {
            $avisos[] = 'SERPER_API_KEY não configurada: cobertura limitada ao OLX/ZAP.';
        }

        $temPopular = collect($empreendimentos)->contains(
            fn(array $e) => Str::contains(strtolower($e['fonte'] ?? ''), ['popular', 'mcmv'])
        );

        if (!$temPopular) {
            $avisos[] = 'IMPORTANTE: Segmento popular/MCMV pode estar subrepresentado.';
        }

        if (empty($empreendimentos)) {
            $avisos[] = 'Nenhum empreendimento encontrado. Verifique diretamente sites das construtoras e CEF.';
        }

        return implode(' | ', $avisos) ?: 'Cobertura incluiu portais e busca web.';
    }

    private function anoMinimo(int $anos): int
    {
        return now()->year - $anos;
    }

    private function serperHttp(): PendingRequest
    {
        return Http::withHeaders([
            'X-API-KEY'    => (string)config('services.serper.key'),
            'Content-Type' => 'application/json',
        ])->timeout(15);
    }

    private function httpNavegador(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'pt-BR,pt;q=0.9',
        ])
            ->timeout(15)
            ->retry(1, 800);
    }
}