<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Terreno;
use App\Services\Dashboard\DashboardQueryService;
use App\Traits\HasDashboardCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

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
