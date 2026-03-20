<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Terreno;
use App\Services\Dashboard\DashboardQueryService;
use App\Traits\HasDashboardCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
    use HasDashboardCache;

    private const OVERVIEW_CACHE_VERSION = 'v2';

    private const OVERVIEW_CACHE_TTL_DEFAULT = 120;

    private const OVERVIEW_CACHE_TTL_MIN = 15;

    private const OVERVIEW_CACHE_TTL_MAX = 600;

    public function __construct(private readonly DashboardQueryService $dashboard) {}

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
     */
    private function cacheDashboardMethod(string $methodName, Request $request, callable $callback): mixed
    {
        $tenantId = tenant('id') ?? 'central';
        $filters = $request->except(['force_refresh']);

        $cacheKey = implode(':', [
            'dashboard',
            $methodName,
            'v1',
            app()->environment(),
            $tenantId,
            md5(json_encode($filters)),
        ]);

        $cacheTag = $this->getDashboardCacheTag();
        $cacheDriver = config('cache.default');
        $supportsTags = in_array($cacheDriver, ['redis', 'memcached']);
        $cacheStore = $supportsTags ? Cache::tags([$cacheTag]) : Cache::getFacadeRoot();
        $forceRefresh = $this->shouldForceRefresh($request);

        if ($forceRefresh) {
            $cacheStore->forget($cacheKey);
            $data = $callback();
            $cacheStore->put($cacheKey, $data, now()->addHours(1));

            return $data;
        }

        return $cacheStore->remember($cacheKey, now()->addHours(1), $callback);
    }

    /**
     * Cards do Dashboard - dados resumidos para exibição nos cards principais.
     */
    public function cards(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('cards', $request, fn () => $this->dashboard->cards());

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Gráfico de Status - total de terrenos agrupados por status.
     */
    public function statusChart(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('statusChart', $request, fn () => $this->dashboard->statusChart($request->input('ano')));

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

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Unidades Fechadas Anual - soma de unidades de terrenos fechados por ano.
     */
    public function unidadesFechadasAnual(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('unidadesFechadasAnual', $request, fn () => $this->dashboard->unidadesFechadasAnual());

        return response()->json(['success' => true, 'data' => $data]);
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

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Anos Disponíveis - lista de anos com cadastros.
     */
    public function anosDisponiveis(): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('anosDisponiveis', request(), fn () => $this->dashboard->anosDisponiveis());

        return response()->json(['success' => true, 'data' => $data]);
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

        return response()->json(['success' => true, 'data' => $data]);
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

            $tenantId = tenant('id') ?? 'central';
            $includeForCache = $include;
            sort($includeForCache, SORT_STRING);
            $cacheKey = implode(':', [
                'dashboard', 'overview', self::OVERVIEW_CACHE_VERSION,
                app()->environment(), $tenantId,
                md5(json_encode([
                    'include' => $includeForCache, 'ano' => $ano, 'mes' => $mes,
                    'meses' => $meses, 'top_limit' => $topLimit,
                    'area_limit' => $areaLimit, 'responsavel_id' => $responsavelId,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ]);

            $cacheTag = $this->getDashboardCacheTag();
            $supportsTags = in_array(config('cache.default'), ['redis', 'memcached']);
            $cacheStore = $supportsTags ? Cache::tags([$cacheTag]) : Cache::getFacadeRoot();
            $forceRefresh = $this->shouldForceRefresh($request);

            $resolver = function () use ($include, $ano, $mes, $meses, $topLimit, $areaLimit, $responsavelId) {
                $payload = [];

                if ($this->shouldInclude($include, 'cards')) {
                    $payload['cards'] = $this->dashboard->cards();
                }

                if ($this->shouldInclude($include, 'status_chart') || $this->shouldInclude($include, 'anos_disponiveis')) {
                    $statusData = $this->dashboard->statusChart($ano);
                    if ($this->shouldInclude($include, 'status_chart')) {
                        $payload['status_chart'] = $statusData['status_data'];
                    }
                    if ($this->shouldInclude($include, 'anos_disponiveis')) {
                        $payload['anos_disponiveis'] = $statusData['anos_disponiveis'];
                    }
                }

                if ($this->shouldInclude($include, 'cadastros_mensais')) {
                    $payload['cadastros_mensais'] = $this->dashboard->cadastrosMensais(
                        ano: $ano, meses: $meses, dataInicio: null, dataFim: null
                    )['cadastros'];
                }

                if ($this->shouldInclude($include, 'top_cidades')) {
                    $filtro = ($ano && $mes) ? 'mes' : ($ano ? 'ano' : 'geral');
                    $payload['top_cidades'] = $this->dashboard->topCidades(
                        filtro: $filtro, ano: $ano, mes: $mes, limit: $topLimit
                    );
                }

                if ($this->shouldInclude($include, 'vgv_anual')) {
                    $payload['vgv_anual'] = $this->dashboard->vgvAnual();
                }

                if ($this->shouldInclude($include, 'unidades_fechadas_anual')) {
                    $payload['unidades_fechadas_anual'] = $this->dashboard->unidadesFechadasAnual();
                }

                if ($this->shouldInclude($include, 'resumo')) {
                    $payload['resumo'] = $this->dashboard->resumoGeral();
                }

                if ($this->shouldInclude($include, 'cadastros_mensais_responsavel')) {
                    $payload['cadastros_mensais_responsavel'] = $this->dashboard->cadastrosMensaisPorResponsavel(
                        ano: $ano, meses: $meses, dataInicio: null, dataFim: null, responsavelId: $responsavelId
                    );
                }

                if ($this->shouldInclude($include, 'area_opcao_detalhe') && $ano) {
                    $payload['area_opcao_detalhe'] = $this->dashboard->areaOpcaoDetalhe(ano: $ano, limit: $areaLimit);
                } elseif ($this->shouldInclude($include, 'area_opcao_detalhe')) {
                    $payload['area_opcao_detalhe'] = [];
                }

                return $payload;
            };

            if ($forceRefresh) {
                $cacheStore->forget($cacheKey);
                $data = $resolver();
                $cacheStore->put($cacheKey, $data, $cacheTtl);
            } else {
                $data = $cacheStore->remember($cacheKey, $cacheTtl, $resolver);
            }

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

    private function shouldInclude(array $include, string $key): bool
    {
        return in_array('*', $include, true) || in_array($key, $include, true);
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
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
