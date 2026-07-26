<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Terreno;
use App\Services\ApiResponseService;
use App\Services\Dashboard\DashboardQueryService;
use App\Services\Tenant\TenantCacheService;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * DashboardController
 *
 * Controller enxuto: apenas autorização, cache e formatação de resposta HTTP.
 * Toda a lógica de consulta reside no DashboardQueryService.
 */
class DashboardController extends Controller
{
    private const OVERVIEW_CACHE_VERSION = 'v2';

    private const OVERVIEW_CACHE_TTL_DEFAULT = 120;

    private const OVERVIEW_CACHE_TTL_MIN = 15;

    private const OVERVIEW_CACHE_TTL_MAX = 600;

    public function __construct(
        private readonly DashboardQueryService $dashboard,
        private readonly TenantCacheService $cache,
    ) {}

    private function authorizeDashboardAccess(): void
    {
        Gate::authorize('viewAny', Terreno::class);
    }

    private function shouldForceRefresh(Request $request): bool
    {
        return $request->boolean('force_refresh', false);
    }

    /**
     * Armazena em cache um callback do dashboard com chave baseada no nome do método + filtros.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    private function cacheDashboardMethod(string $methodName, Request $request, Closure $callback): mixed
    {
        $cacheKey = $this->cache->key('dashboard', "{$methodName}:v1", [
            'environment' => app()->environment(),
            'filters' => $this->cacheFilters($methodName, $request),
        ]);

        return $this->cache->remember(
            'dashboard',
            $cacheKey,
            now()->addHours(1),
            $callback,
            $this->shouldForceRefresh($request),
        );
    }

    /**
     * Cards do Dashboard - dados resumidos para exibição nos cards principais.
     */
    public function cards(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('cards', $request, fn () => $this->dashboard->cards());

        return ApiResponseService::success($data);
    }

    /**
     * Visão gerencial consolidada para leitura rápida da carteira.
     */
    public function managementOverview(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $staleDays = (int) $request->input('stale_days', 30);
        $criticalDays = (int) $request->input('critical_days', 15);
        $limit = (int) $request->input('limit', 8);

        $data = $this->cacheDashboardMethod('managementOverview', $request, fn () => $this->dashboard->managementOverview(
            staleDays: $staleDays,
            criticalDays: $criticalDays,
            limit: $limit,
        ));

        return ApiResponseService::success($data);
    }

    /**
     * Gráfico de Status - total de terrenos agrupados por status.
     */
    public function statusChart(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('statusChart', $request, fn () => $this->dashboard->statusChart($request->input('ano'), $request->input('data_inicio')));

        return response()->json([
            'success' => true,
            'filters' => [
                'ano' => $request->input('ano') ?? null,
                'anos_disponiveis' => $data['anos_disponiveis'],
            ],
            'data' => $data['status_data'],
        ]);
    }

    /**
     * Cadastros Mensais - quantidade de terrenos cadastrados por mês.
     */
    public function cadastrosMensais(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('cadastrosMensais', $request, fn () => $this->dashboard->cadastrosMensais(
            ano: $request->input('ano'),
            meses: (int) $request->input('meses', 12),
            dataInicio: $request->input('data_inicio'),
            dataFim: $request->input('data_fim'),
        ));

        return response()->json([
            'success' => true,
            'filters' => $data['filters'],
            'data' => $data['cadastros'],
        ]);
    }

    /**
     * Top Cidades - total de terrenos agrupados por cidade.
     */
    public function topCidades(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $filtro = $request->input('filtro', 'geral');
        $ano = $request->input('ano');
        $mes = $request->input('mes');
        $limit = (int) $request->input('limit', 10);

        if (in_array($filtro, ['ano', 'mes']) && ! $ano) {
            return response()->json(['success' => false, 'message' => 'Ano é obrigatório para filtros "ano" ou "mes"'], 422);
        }

        if ($filtro === 'mes' && ! $mes) {
            return response()->json(['success' => false, 'message' => 'Mês é obrigatório para filtro "mes"'], 422);
        }

        $data = $this->cacheDashboardMethod('topCidades', $request, fn () => $this->dashboard->topCidades(
            filtro: $filtro,
            ano: $ano,
            mes: $mes,
            limit: $limit
        ));

        return response()->json([
            'success' => true,
            'filters' => [
                'filtro' => $filtro,
                'ano' => $ano ?? null,
                'mes' => $mes ?? null,
                'mes_nome' => $mes ? Carbon::create(2024, $mes)->translatedFormat('F') : null,
                'limit' => $limit,
            ],
            'data' => $data,
        ]);
    }

    /**
     * VGV Anual - soma do VGV das áreas com opção agrupado por ano.
     */
    public function vgvAnual(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('vgvAnual', $request, fn () => $this->dashboard->vgvAnual());

        return ApiResponseService::success($data);
    }

    /**
     * Unidades Fechadas Anual - soma de unidades de terrenos fechados por ano.
     */
    public function unidadesFechadasAnual(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('unidadesFechadasAnual', $request, fn () => $this->dashboard->unidadesFechadasAnual());

        return ApiResponseService::success($data);
    }

    /**
     * Cadastros Mensais por Responsável - quantidade mensal de cadastros agrupada por responsável.
     */
    public function cadastrosMensaisPorResponsavel(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $meses = (int) $request->input('meses', 12);
        $ano = $request->input('ano');
        $responsavelId = $request->input('responsavel_id');

        if ($meses < 1 || $meses > 60) {
            return response()->json(['success' => false, 'message' => 'O parâmetro meses deve estar entre 1 e 60'], 422);
        }

        $data = $this->cacheDashboardMethod('cadastrosMensaisPorResponsavel', $request, fn () => $this->dashboard->cadastrosMensaisPorResponsavel(
            ano: $ano,
            meses: $meses,
            dataInicio: $request->input('data_inicio'),
            dataFim: $request->input('data_fim'),
            responsavelId: $responsavelId,
        ));

        return response()->json([
            'success' => true,
            'filters' => [
                'ano' => $ano ?? null,
                'meses' => $ano ? null : $meses,
                'responsavel_id' => $responsavelId ?? null,
            ],
            'data' => $data,
        ]);
    }

    /**
     * Resumo Geral - dados consolidados do dashboard.
     */
    public function resumoGeral(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('resumoGeral', $request, fn () => $this->dashboard->resumoGeral());

        return ApiResponseService::success($data);
    }

    /**
     * Anos Disponíveis - lista de anos com cadastros.
     */
    public function anosDisponiveis(): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('anosDisponiveis', request(), fn () => $this->dashboard->anosDisponiveis());

        return ApiResponseService::success($data);
    }

    /**
     * Área Opção Detalhe - terrenos em opção de um ano específico com VGV e unidades.
     */
    public function areaOpcaoDetalhe(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $ano = $request->input('ano');
        $limit = $request->input('limit') ? (int) $request->input('limit') : null;

        if (! $ano) {
            return response()->json(['success' => false, 'message' => 'O parâmetro ano é obrigatório'], 422);
        }

        $data = $this->cacheDashboardMethod('areaOpcaoDetalhe', $request, fn () => $this->dashboard->areaOpcaoDetalhe(
            ano: $ano,
            limit: $limit
        ));

        return ApiResponseService::success($data);
    }

    /**
     * Overview do Dashboard - endpoint agregador para reduzir múltiplas chamadas.
     */
    public function overview(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        try {
            $include = $this->parseInclude($request->input('include'));
            $ano = $request->input('ano');
            $mes = $request->input('mes');
            $meses = (int) $request->input('meses', 12);
            $topLimit = (int) $request->input('top_cidades_limit', $request->input('limit', 10));
            $areaLimit = (int) $request->input('area_opcao_limit', $request->input('limit', 10));
            $responsavelId = $request->input('responsavel_id');
            $cacheTtlRaw = (int) $request->input('cache_ttl', config('cache.dashboard_overview_ttl', self::OVERVIEW_CACHE_TTL_DEFAULT));
            $cacheTtl = max(self::OVERVIEW_CACHE_TTL_MIN, min(self::OVERVIEW_CACHE_TTL_MAX, $cacheTtlRaw));

            $includeForCache = $include;
            sort($includeForCache, SORT_STRING);
            $cacheKey = $this->cache->key('dashboard', 'overview:'.self::OVERVIEW_CACHE_VERSION, [
                'environment' => app()->environment(),
                'filters' => [
                    'include' => $includeForCache, 'ano' => $ano, 'mes' => $mes,
                    'meses' => $meses, 'top_limit' => $topLimit,
                    'area_limit' => $areaLimit, 'responsavel_id' => $responsavelId,
                ],
            ]);

            $resolver = fn (): array => $this->dashboard->buildOverview(
                $include, $ano, $mes, $meses, $topLimit, $areaLimit, $responsavelId
            );

            $data = $this->cache->remember(
                'dashboard',
                $cacheKey,
                $cacheTtl,
                $resolver,
                $this->shouldForceRefresh($request),
            );

            return response()->json([
                'success' => true,
                'filters' => [
                    'ano' => $ano ?? null,
                    'mes' => $mes ?? null,
                    'meses' => $meses,
                    'top_cidades_limit' => $topLimit,
                    'area_opcao_limit' => $areaLimit,
                    'responsavel_id' => $responsavelId ?? null,
                ],
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar overview do dashboard: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar overview do dashboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Erro interno do servidor',
            ], 500);
        }
    }

    private function parseInclude(?string $raw): array
    {
        if (! $raw) {
            return [
                'cards', 'anos_disponiveis', 'status_chart', 'cadastros_mensais',
                'top_cidades', 'vgv_anual', 'resumo', 'cadastros_mensais_responsavel',
                'area_opcao_detalhe',
            ];
        }

        return collect(explode(',', $raw))
            ->map(fn ($i) => trim($i))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function cacheFilters(string $methodName, Request $request): array
    {
        return match ($methodName) {
            'managementOverview' => [
                'stale_days' => $request->integer('stale_days', 30),
                'critical_days' => $request->integer('critical_days', 15),
                'limit' => $request->integer('limit', 8),
            ],
            'statusChart' => $request->only(['ano', 'data_inicio']),
            'cadastrosMensais' => [
                ...$request->only(['ano', 'data_inicio', 'data_fim']),
                'meses' => $request->integer('meses', 12),
            ],
            'topCidades', 'terrenosPorResponsavel' => [
                ...$request->only(['ano', 'mes']),
                'filtro' => $request->input('filtro', 'geral'),
                'limit' => $request->input('limit'),
            ],
            'cadastrosMensaisPorResponsavel' => [
                ...$request->only(['ano', 'data_inicio', 'data_fim', 'responsavel_id']),
                'meses' => $request->integer('meses', 12),
            ],
            'areaOpcaoDetalhe' => [
                'ano' => $request->input('ano'),
                'limit' => $request->input('limit'),
            ],
            default => [],
        };
    }

    /**
     * Terrenos por Responsável - total de terrenos agrupados por responsável.
     */
    public function terrenosPorResponsavel(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        try {
            $filtro = $request->input('filtro', 'geral');
            $ano = $request->input('ano');
            $mes = $request->input('mes');
            $limit = $request->input('limit');

            if (in_array($filtro, ['ano', 'mes']) && ! $ano) {
                return response()->json(['success' => false, 'message' => 'Ano é obrigatório para filtros "ano" ou "mes"'], 422);
            }

            if ($filtro === 'mes' && ! $mes) {
                return response()->json(['success' => false, 'message' => 'Mês é obrigatório para filtro "mes"'], 422);
            }

            $data = $this->cacheDashboardMethod('terrenosPorResponsavel', $request, fn () => $this->dashboard->terrenosPorResponsavel(
                filtro: $filtro,
                ano: $ano,
                mes: $mes,
                limit: $limit
            ));

            return response()->json([
                'success' => true,
                'filters' => [
                    'filtro' => $filtro,
                    'ano' => $ano,
                    'mes' => $mes,
                ],
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro no dashboard', ['exception' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Erro interno no servidor.'], 500);
        }
    }
}
