<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\TerrenoStatus;
use App\Support\Database\SqlDateParts;
use App\Traits\HasDashboardCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * DashboardControllerV2
 * 
 * Versão 2 do controller com suporte a filtros avançados
 * 
 * @package App\Http\Controllers\Api
 */
class DashboardController extends Controller
{
    use HasDashboardCache;

    private function authorizeDashboardAccess(): void
    {
        Gate::authorize('viewAny', Terreno::class);
    }

    /**
     * Status considerado como "opção" para cálculos
     */
    private const STATUS_OPCAO = 'Opção';

    /**
     * Status considerado como "fechado" para cálculos
     */
    private const STATUS_FECHADO = 'Fechado';

    private const OVERVIEW_CACHE_VERSION = 'v2';
    private const OVERVIEW_CACHE_TTL_DEFAULT = 120;
    private const OVERVIEW_CACHE_TTL_MIN = 15;
    private const OVERVIEW_CACHE_TTL_MAX = 600;

    /**
     * Cache de IDs de status para evitar múltiplas consultas
     */
    private ?int $statusOpcaoIdCache = null;
    private ?int $statusFechadoIdCache = null;

    private function shouldForceRefresh(Request $request): bool
    {
        return $request->boolean('force_refresh', false);
    }

    /**
     * Obtém o ID do status 'Opção'
     */
    private function getStatusOpcaoId(): ?int
    {
        if ($this->statusOpcaoIdCache === null) {
            $status = TerrenoStatus::where('nome', 'like', '%' . self::STATUS_OPCAO . '%')->first();
            $this->statusOpcaoIdCache = $status?->id;
        }
        return $this->statusOpcaoIdCache;
    }

    /**
     * Obtém o ID do status 'Fechado'
     */
    private function getStatusFechadoId(): ?int
    {
        if ($this->statusFechadoIdCache === null) {
            $status = TerrenoStatus::where('nome', 'like', '%' . self::STATUS_FECHADO . '%')->first();
            $this->statusFechadoIdCache = $status?->id;
        }
        return $this->statusFechadoIdCache;
    }

    /**
     * Helper para cachear métodos do dashboard
     */
    private function cacheDashboardMethod(string $methodName, Request $request, callable $callback)
    {
        $tenantId = tenant('id') ?? 'central';
        $filters = $request->except(['force_refresh']);
        
        $cacheKey = implode(':', [
            'dashboard',
            $methodName,
            'v1',
            app()->environment(),
            $tenantId,
            md5(json_encode($filters))
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

        // Cache por 1 hora
        return $cacheStore->remember($cacheKey, now()->addHours(1), $callback);
    }

    /**
     * Cards do Dashboard - Retorna dados resumidos para exibição nos cards principais
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function cards(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('cards', $request, function () {
            // Buscar o ID do status 'opção'
            $statusOpcaoId = $this->getStatusOpcaoId();

            // Total de terrenos cadastrados
            $totalTerrenos = Terreno::count();

            // Total de terrenos com status 'opção'
            $totalOpcao = $statusOpcaoId
                ? Terreno::where('status_id', $statusOpcaoId)->count()
                : 0;

            // Total de unidades das áreas com status 'opção'
            $totalUnidadesOpcao = $statusOpcaoId
                ? TerrenoProduto::join('terrenos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
                    ->where('terrenos.status_id', $statusOpcaoId)
                    ->whereNull('terrenos.deleted_at')
                    ->sum('terreno_produtos.unidades')
                : 0;

            // Distribuição por cidade (top 20 para não sobrecarregar)
            $porCidade = Terreno::select('cidade_code', DB::raw('COUNT(*) as total'))
                ->with('cidade:code,city,state_code')
                ->whereNotNull('cidade_code')
                ->where('cidade_code', '!=', '')
                ->groupBy('cidade_code')
                ->orderByDesc('total')
                ->limit(20)
                ->get()
                ->map(function ($item) {
                    return [
                        'cidade' => $item->cidade?->city ?? 'Não Informada',
                        'estado' => $item->cidade?->state_code ?? '-',
                        'total' => $item->total,
                    ];
                });

            return [
                'total_terrenos' => $totalTerrenos,
                'total_opcao' => $totalOpcao,
                'total_unidades_opcao' => (int) $totalUnidadesOpcao,
                'distribuicao_cidades' => $porCidade,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Gráfico de Status - Retorna total de terrenos agrupados por status
     * Com suporte a filtro por ano
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function statusChart(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('statusChart', $request, function () use ($request) {
            $ano = $request->input('ano');
            $incluirAnos = $request->boolean('incluir_anos', false);

            // Buscar todos os anos disponíveis
            $anosDisponiveis = Terreno::select(DB::raw('DISTINCT ' . SqlDateParts::year('created_at') . ' as ano'))
                ->whereNotNull('created_at')
                ->orderBy('ano', 'desc')
                ->pluck('ano')
                ->toArray();

            // Query base
            $query = Terreno::select('status_id', DB::raw('COUNT(*) as total'))
                ->with('terrenoStatus:id,nome,cor');

            // Filtro por ano
            if ($ano) {
                $query->whereYear('created_at', $ano);
            }

            $statusData = $query
                ->groupBy('status_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'status_id' => $item->status_id,
                        'status_nome' => $item->terrenoStatus?->nome ?? 'Sem Status',
                        'status_cor' => $item->terrenoStatus?->cor ?? '#cccccc',
                        'total' => $item->total,
                    ];
                });

            return [
                'status_data' => $statusData,
                'anos_disponiveis' => $anosDisponiveis,
            ];
        });

        return response()->json([
            'success' => true,
            'filters' => [
                'ano' => $request->input('ano') ?? null,
                'anos_disponiveis' => $data['anos_disponiveis'],
            ],
            'data' => $data['status_data'],
        ], 200);
    }

    /**
     * Cadastros Mensais - Retorna quantidade de terrenos cadastrados por mês
     * Com suporte a filtro por ano
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * Query params opcionais:
     * - meses: número de meses para buscar (padrão: 12)
     * - ano: filtrar por ano específico
     * - data_inicio: data de início do período (formato: Y-m-d)
     * - data_fim: data de fim do período (formato: Y-m-d)
     */
    public function cadastrosMensais(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('cadastrosMensais', $request, function () use ($request) {
            $meses = $request->input('meses', 12);
            $ano = $request->input('ano');
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');

            $query = Terreno::select(
                DB::raw(SqlDateParts::yearAs('created_at', 'ano')),
                DB::raw(SqlDateParts::monthAs('created_at', 'mes')),
                DB::raw('COUNT(*) as total')
            );

            // Filtro por ano específico
            if ($ano) {
                $query->whereYear('created_at', $ano);
            } elseif ($dataInicio && $dataFim) {
                $query->whereBetween('created_at', [
                    Carbon::parse($dataInicio)->startOfDay(),
                    Carbon::parse($dataFim)->endOfDay()
                ]);
            } else {
                // Default: últimos N meses
                $query->where('created_at', '>=', Carbon::now()->subMonths($meses)->startOfMonth());
            }

            $cadastros = $query
                ->groupBy(DB::raw(SqlDateParts::year('created_at')), DB::raw(SqlDateParts::month('created_at')))
                ->orderBy('ano', 'asc')
                ->orderBy('mes', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'ano' => $item->ano,
                        'mes' => $item->mes,
                        'mes_nome' => Carbon::create($item->ano, $item->mes)->translatedFormat('F'),
                        'periodo' => Carbon::create($item->ano, $item->mes)->format('Y-m'),
                        'total' => $item->total,
                    ];
                });

            return [
                'cadastros' => $cadastros,
                'filters' => [
                    'ano' => $ano ?? null,
                    'meses' => $ano ? null : $meses,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'filters' => $data['filters'],
            'data' => $data['cadastros'],
        ], 200);
    }

    /**
     * Terrenos por Responsável - Retorna total de terrenos agrupados por responsável
     * Com suporte a filtros por geral, ano e mês do ano
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * Query params opcionais:
     * - filtro: 'geral' | 'ano' | 'mes' (padrão: 'geral')
     * - ano: ano para filtro (obrigatório se filtro='ano' ou 'mes')
     * - mes: mês para filtro (obrigatório se filtro='mes')
     * - limit: limitar quantidade de resultados (padrão: sem limite)
     */
    public function terrenosPorResponsavel(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        try {
            $filtro = $request->input('filtro', 'geral');
            $ano = $request->input('ano');
            $mes = $request->input('mes');
            $limit = $request->input('limit');

            // Validação
            if (in_array($filtro, ['ano', 'mes']) && !$ano) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ano é obrigatório para filtros "ano" ou "mes"',
                ], 422);
            }

            if ($filtro === 'mes' && !$mes) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mês é obrigatório para filtro "mes"',
                ], 422);
            }

            $query = Terreno::select('responsavel_id', DB::raw('COUNT(*) as total'))
                ->with('responsavel:id,name,email')
                ->whereNotNull('responsavel_id');

            // Aplicar filtro
            if ($filtro === 'ano' && $ano) {
                $query->whereYear('created_at', $ano);
            } elseif ($filtro === 'mes' && $ano && $mes) {
                $query->whereYear('created_at', $ano)
                    ->whereMonth('created_at', $mes);
            }
            // 'geral' não adiciona filtro de data

            $query->groupBy('responsavel_id')
                ->orderByDesc('total');

            if ($limit && is_numeric($limit) && $limit > 0) {
                $query->limit((int) $limit);
            }

            $terrenos = $query->get()->map(function ($item) {
                return [
                    'responsavel_id' => $item->responsavel_id,
                    'responsavel_nome' => $item->responsavel?->name ?? 'Não informado',
                    'responsavel_email' => $item->responsavel?->email ?? null,
                    'total' => $item->total,
                ];
            });

            return response()->json([
                'success' => true,
                'filters' => [
                    'filtro' => $filtro,
                    'ano' => $ano ?? null,
                    'mes' => $mes ?? null,
                    'mes_nome' => $mes ? Carbon::create(2024, $mes)->translatedFormat('F') : null,
                ],
                'data' => $terrenos,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar terrenos por responsável: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar terrenos por responsável',
                'error' => config('app.debug') ? $e->getMessage() : 'Erro interno do servidor',
            ], 500);
        }
    }

    /**
     * Top 10 Cidades - Retorna as 10 cidades com mais cadastros
     * Com suporte a filtros por geral, ano e mês do ano
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * Query params opcionais:
     * - filtro: 'geral' | 'ano' | 'mes' (padrão: 'geral')
     * - ano: ano para filtro (obrigatório se filtro='ano' ou 'mes')
     * - mes: mês para filtro (obrigatório se filtro='mes')
     * - limit: número de cidades a retornar (padrão: 10)
     */
    public function topCidades(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $filtro = $request->input('filtro', 'geral');
        $ano = $request->input('ano');
        $mes = $request->input('mes');
        $limit = $request->input('limit', 10);

        // Validação
        if (in_array($filtro, ['ano', 'mes']) && !$ano) {
            return response()->json([
                'success' => false,
                'message' => 'Ano é obrigatório para filtros "ano" ou "mes"',
            ], 422);
        }

        if ($filtro === 'mes' && !$mes) {
            return response()->json([
                'success' => false,
                'message' => 'Mês é obrigatório para filtro "mes"',
            ], 422);
        }

        $data = $this->cacheDashboardMethod('topCidades', $request, function () use ($filtro, $ano, $mes, $limit) {
            $query = Terreno::select('cidade_code', DB::raw('COUNT(*) as total'))
                ->with('cidade:code,city,state_code')
                ->whereNotNull('cidade_code')
                ->where('cidade_code', '!=', '');

            // Aplicar filtro
            if ($filtro === 'ano' && $ano) {
                $query->whereYear('created_at', $ano);
            } elseif ($filtro === 'mes' && $ano && $mes) {
                $query->whereYear('created_at', $ano)
                    ->whereMonth('created_at', $mes);
            }
            // 'geral' não adiciona filtro de data

            return $query
                ->groupBy('cidade_code')
                ->orderByDesc('total')
                ->limit((int) $limit)
                ->get()
                ->map(function ($item, $index) {
                    return [
                        'posicao' => $index + 1,
                        'cidade_code' => $item->cidade_code,
                        'cidade' => $item->cidade?->city,
                        'estado' => $item->cidade?->state_code,
                        'total' => $item->total,
                    ];
                });
        });

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
        ], 200);
    }

    /**
     * VGV Anual - Retorna a soma do VGV das áreas com status 'opção' agrupado por ano
     * 
     * @return JsonResponse
     */
    public function vgvAnual(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('vgvAnual', $request, function () {
            $statusOpcaoId = $this->getStatusOpcaoId();

            if (!$statusOpcaoId) {
                return [];
            }

            return Terreno::leftJoin('terreno_produtos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
                ->where('terrenos.status_id', $statusOpcaoId)
                ->whereNull('terrenos.deleted_at')
                ->select(
                    DB::raw(SqlDateParts::yearAs('COALESCE(terrenos.data_opcao, terrenos.created_at)', 'ano')),
                    DB::raw('SUM(COALESCE(terreno_produtos.valor, 0) * COALESCE(terreno_produtos.unidades, 0)) as vgv_total'),
                    DB::raw('SUM(COALESCE(terreno_produtos.unidades, 0)) as total_unidades'),
                    DB::raw('COUNT(DISTINCT terrenos.id) as total_terrenos')
                )
                ->groupBy(DB::raw(SqlDateParts::year('COALESCE(terrenos.data_opcao, terrenos.created_at)')))
                ->orderBy('ano', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'ano' => $item->ano,
                        'vgv_total' => (float) $item->vgv_total,
                        'vgv_formatado' => 'R$ ' . number_format($item->vgv_total, 2, ',', '.'),
                        'total_unidades' => (int) $item->total_unidades,
                        'total_terrenos' => $item->total_terrenos,
                        'total_areas' => $item->total_terrenos,
                    ];
                });
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Unidades Fechadas por Ano - Retorna a soma de unidades de terrenos com status 'fechado' por ano
     * 
     * @return JsonResponse
     */
    public function unidadesFechadasAnual(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('unidadesFechadasAnual', $request, function () {
            $statusFechadoId = $this->getStatusFechadoId();

            if (!$statusFechadoId) {
                return [];
            }

            return TerrenoProduto::join('terrenos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
                ->where('terrenos.status_id', $statusFechadoId)
                ->whereNull('terrenos.deleted_at')
                ->select(
                    DB::raw(SqlDateParts::yearAs('terrenos.data_contrato', 'ano')),
                    DB::raw('SUM(COALESCE(terreno_produtos.unidades, 0)) as total_unidades'),
                    DB::raw('COUNT(DISTINCT terrenos.id) as total_terrenos')
                )
                ->whereNotNull('terrenos.data_contrato')
                ->groupBy(DB::raw(SqlDateParts::year('terrenos.data_contrato')))
                ->orderBy('ano', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'ano' => $item->ano,
                        'total_unidades' => (int) $item->total_unidades,
                        'total_terrenos' => $item->total_terrenos,
                        'total_areas' => $item->total_terrenos,
                    ];
                });
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Cadastros Mensais por Responsável - Retorna quantidade mensal de cadastros por responsável
     * Com suporte a filtro por ano
     * 
     * @param Request $request
     * @return JsonResponse
     * 
     * Query params opcionais:
     * - meses: número de meses para buscar (padrão: 12)
     * - ano: filtrar por ano específico
     * - data_inicio: data de início do período (formato: Y-m-d)
     * - data_fim: data de fim do período (formato: Y-m-d)
     * - responsavel_id: filtrar por responsável específico
     */
    public function cadastrosMensaisPorResponsavel(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $meses = $request->input('meses', 12);
        $ano = $request->input('ano');
        $responsavelId = $request->input('responsavel_id');

        // Validação básica
        if ($meses < 1 || $meses > 60) {
            return response()->json([
                'success' => false,
                'message' => 'O parâmetro meses deve estar entre 1 e 60',
            ], 422);
        }

        $data = $this->cacheDashboardMethod('cadastrosMensaisPorResponsavel', $request, function () use ($request, $meses, $ano, $responsavelId) {
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');

            $query = Terreno::select(
                'responsavel_id',
                DB::raw(SqlDateParts::yearAs('created_at', 'ano')),
                DB::raw(SqlDateParts::monthAs('created_at', 'mes')),
                DB::raw('COUNT(*) as total')
            )
                ->with('responsavel:id,name')
                ->whereNotNull('responsavel_id');

            // Filtro por responsável específico
            if ($responsavelId) {
                $query->where('responsavel_id', $responsavelId);
            }

            // Filtro por período
            if ($ano) {
                $query->whereYear('created_at', $ano);
            } elseif ($dataInicio && $dataFim) {
                $query->whereBetween('created_at', [
                    Carbon::parse($dataInicio)->startOfDay(),
                    Carbon::parse($dataFim)->endOfDay()
                ]);
            } else {
                $query->where('created_at', '>=', Carbon::now()->subMonths($meses)->startOfMonth());
            }

            $cadastros = $query
                ->groupBy(
                    'responsavel_id',
                    DB::raw(SqlDateParts::year('created_at')),
                    DB::raw(SqlDateParts::month('created_at'))
                )
                ->orderBy('ano', 'desc')
                ->orderBy('mes', 'desc')
                ->orderByDesc('total')
                ->get()
                ->map(function ($item) {
                    return [
                        'responsavel_id' => $item->responsavel_id,
                        'responsavel_nome' => $item->responsavel?->name ?? 'Não informado',
                        'ano' => $item->ano,
                        'mes' => $item->mes,
                        'mes_nome' => Carbon::create($item->ano, $item->mes)->translatedFormat('F'),
                        'periodo' => Carbon::create($item->ano, $item->mes)->format('Y-m'),
                        'total' => $item->total,
                    ];
                });

            // Agrupar por responsável para facilitar a visualização
            return $cadastros->groupBy('responsavel_id')->map(function ($items, $responsavelId) {
                $primeiro = $items->first();
                return [
                    'responsavel_id' => $responsavelId,
                    'responsavel_nome' => $primeiro['responsavel_nome'],
                    'total_geral' => $items->sum('total'),
                    'mensal' => $items->map(function ($item) {
                        return [
                            'ano' => $item['ano'],
                            'mes' => $item['mes'],
                            'mes_nome' => $item['mes_nome'],
                            'periodo' => $item['periodo'],
                            'total' => $item['total'],
                        ];
                    })->values(),
                ];
            })->sortByDesc('total_geral')->values();
        });

        return response()->json([
            'success' => true,
            'filters' => [
                'ano' => $ano ?? null,
                'meses' => $ano ? null : $meses,
                'responsavel_id' => $responsavelId ?? null,
            ],
            'data' => $data,
        ], 200);
    }

    /**
     * Resumo Geral - Endpoint único que retorna todos os dados do dashboard
     * Útil para carregamento inicial do dashboard
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function resumoGeral(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('resumoGeral', $request, function () {
            // Buscar status IDs
            $statusOpcaoId = $this->getStatusOpcaoId();
            $statusFechadoId = $this->getStatusFechadoId();

            // Total de terrenos
            $totalTerrenos = Terreno::count();

            // Total em opção
            $totalOpcao = $statusOpcaoId ? Terreno::where('status_id', $statusOpcaoId)->count() : 0;

            // Total fechados
            $totalFechados = $statusFechadoId ? Terreno::where('status_id', $statusFechadoId)->count() : 0;

            // Total unidades opção
            $totalUnidadesOpcao = $statusOpcaoId
                ? TerrenoProduto::whereHas('terreno', fn($q) => $q->where('status_id', $statusOpcaoId))->sum('unidades')
                : 0;

            // Total unidades fechadas
            $totalUnidadesFechadas = $statusFechadoId
                ? TerrenoProduto::whereHas('terreno', fn($q) => $q->where('status_id', $statusFechadoId))->sum('unidades')
                : 0;

            // Cadastros do mês atual
            $cadastrosMesAtual = Terreno::whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->count();

            // Top 5 responsáveis
            $topResponsaveis = Terreno::select('responsavel_id', DB::raw('COUNT(*) as total'))
                ->with('responsavel:id,name')
                ->whereNotNull('responsavel_id')
                ->groupBy('responsavel_id')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(fn($item) => [
                    'nome' => $item->responsavel?->name ?? 'Não informado',
                    'total' => $item->total,
                ]);

            // Distribuição por status
            $distribuicaoStatus = Terreno::select('status_id', DB::raw('COUNT(*) as total'))
                ->with('terrenoStatus:id,nome,cor')
                ->groupBy('status_id')
                ->get()
                ->map(fn($item) => [
                    'status' => $item->terrenoStatus?->nome ?? 'Sem Status',
                    'cor' => $item->terrenoStatus?->cor ?? '#cccccc',
                    'total' => $item->total,
                ]);

            return [
                'totais' => [
                    'terrenos' => $totalTerrenos,
                    'opcao' => $totalOpcao,
                    'fechados' => $totalFechados,
                    'unidades_opcao' => (int) $totalUnidadesOpcao,
                    'unidades_fechadas' => (int) $totalUnidadesFechadas,
                    'cadastros_mes_atual' => $cadastrosMesAtual,
                ],
                'top_responsaveis' => $topResponsaveis,
                'distribuicao_status' => $distribuicaoStatus,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Getter de anos disponíveis - Retorna todos os anos de cadastro do sistema
     * 
     * @return JsonResponse
     */
    public function anosDisponiveis(): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $data = $this->cacheDashboardMethod('anosDisponiveis', request(), function () {
            return Terreno::select(DB::raw('DISTINCT ' . SqlDateParts::year('created_at') . ' as ano'))
                ->whereNotNull('created_at')
                ->orderBy('ano', 'desc')
                ->pluck('ano')
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Terrenos Opção por Ano (Detalhado) - Retorna lista de terrenos em opção de um ano específico
     * com VGV e unidades calculados.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function areaOpcaoDetalhe(Request $request): JsonResponse
    {
        $this->authorizeDashboardAccess();

        $ano = $request->input('ano');
        $limit = $request->input('limit');

        if (!$ano) {
            return response()->json([
                'success' => false,
                'message' => 'O parâmetro ano é obrigatório',
            ], 422);
        }

        $data = $this->cacheDashboardMethod('areaOpcaoDetalhe', $request, function () use ($ano, $limit) {
            $statusOpcaoId = $this->getStatusOpcaoId();

            if (!$statusOpcaoId) {
                return [];
            }

            $query = Terreno::query()
                ->where('status_id', $statusOpcaoId)
                ->whereYear('data_opcao', $ano)
                ->with(['cidade', 'responsavel'])
                ->withSum('terrenoProdutos as total_unidades', 'unidades')
                ->addSelect([
                    'vgv_total' => TerrenoProduto::select(DB::raw('SUM(COALESCE(valor, 0) * COALESCE(unidades, 0))'))
                        ->whereColumn('terreno_id', 'terrenos.id'),
                ]);

            if ($limit) {
                $query->limit((int) $limit);
            }

            return $query->orderByDesc('vgv_total')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'nome' => $item->nome,
                        'cidade' => $item->cidade?->city,
                        'estado' => $item->cidade?->state_code,
                        'responsavel' => $item->responsavel?->name,
                        'total_unidades' => (int) $item->total_unidades,
                        'vgv_total' => (float) $item->vgv_total,
                        'vgv_formatado' => 'R$ ' . number_format($item->vgv_total, 2, ',', '.'),
                    ];
                });
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Overview do Dashboard - Endpoint agregador para reduzir múltiplas chamadas
     *
     * Query params opcionais:
     * - include: lista separada por vírgula de blocos (ex: cards,anos_disponiveis,status_chart,...)
     * - ano: filtro por ano
     * - mes: filtro por mês
     * - meses: período para cadastros mensais (padrão 12)
     * - top_cidades_limit: limite de cidades (padrão 10)
     * - area_opcao_limit: limite de áreas na opção (padrão 10)
     * - responsavel_id: filtro para cadastros mensais por responsável
     * - cache_ttl: segundos de cache (padrão 60)
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
            $cacheTtlRaw = (int) $request->input(
                'cache_ttl',
                config('cache.dashboard_overview_ttl', self::OVERVIEW_CACHE_TTL_DEFAULT)
            );
            $cacheTtl = max(self::OVERVIEW_CACHE_TTL_MIN, min(self::OVERVIEW_CACHE_TTL_MAX, $cacheTtlRaw));

            $tenantId = tenancy()->initialized ? tenancy()->tenant->id : 'central';
            $includeForCache = $include;
            sort($includeForCache, SORT_STRING);
            $cacheKey = implode(':', [
                'dashboard',
                'overview',
                self::OVERVIEW_CACHE_VERSION,
                app()->environment(),
                $tenantId,
                md5(json_encode([
                    'include' => $includeForCache,
                    'ano' => $ano,
                    'mes' => $mes,
                    'meses' => $meses,
                    'top_limit' => $topLimit,
                    'area_limit' => $areaLimit,
                    'responsavel_id' => $responsavelId,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ]);

            $cacheTag = $this->getDashboardCacheTag();
            $cacheDriver = config('cache.default');
            $supportsTags = in_array($cacheDriver, ['redis', 'memcached']);
            $forceRefresh = $this->shouldForceRefresh($request);

            $cacheStore = $supportsTags ? Cache::tags([$cacheTag]) : Cache::getFacadeRoot();

            $resolver = function () use (
                $include,
                $request,
                $ano,
                $mes,
                $meses,
                $topLimit,
                $areaLimit,
                $responsavelId
            ) {
                $payload = [];

                if ($this->shouldInclude($include, 'cards')) {
                    $payload['cards'] = $this->extractResponseData($this->cards($request));
                }

                if ($this->shouldInclude($include, 'status_chart') || $this->shouldInclude($include, 'anos_disponiveis')) {
                    $statusResponse = $this->statusChart($request);
                    $statusPayload = $this->extractResponsePayload($statusResponse);
                    if ($this->shouldInclude($include, 'status_chart')) {
                        $payload['status_chart'] = $statusPayload['data'] ?? [];
                    }
                    if ($this->shouldInclude($include, 'anos_disponiveis')) {
                        $payload['anos_disponiveis'] = $statusPayload['filters']['anos_disponiveis'] ?? [];
                    }
                }

                if ($this->shouldInclude($include, 'cadastros_mensais')) {
                    $cadastrosRequest = $request->duplicate();
                    $cadastrosRequest->merge([
                        'ano' => $ano,
                        'meses' => $meses,
                    ]);
                    $payload['cadastros_mensais'] = $this->extractResponseData($this->cadastrosMensais($cadastrosRequest)) ?? [];
                }

                if ($this->shouldInclude($include, 'top_cidades')) {
                    $topRequest = $request->duplicate();
                    $filtro = ($ano && $mes) ? 'mes' : ($ano ? 'ano' : 'geral');
                    $topRequest->merge([
                        'filtro' => $filtro,
                        'ano' => $ano,
                        'mes' => $ano ? $mes : null,
                        'limit' => $topLimit,
                    ]);
                    $payload['top_cidades'] = $this->extractResponseData($this->topCidades($topRequest)) ?? [];
                }

                if ($this->shouldInclude($include, 'vgv_anual')) {
                    $payload['vgv_anual'] = $this->extractResponseData($this->vgvAnual($request)) ?? [];
                }

                if ($this->shouldInclude($include, 'unidades_fechadas_anual')) {
                    $payload['unidades_fechadas_anual'] = $this->extractResponseData($this->unidadesFechadasAnual($request)) ?? [];
                }

                if ($this->shouldInclude($include, 'resumo')) {
                    $payload['resumo'] = $this->extractResponseData($this->resumoGeral($request));
                }

                if ($this->shouldInclude($include, 'cadastros_mensais_responsavel')) {
                    $cadastrosRespRequest = $request->duplicate();
                    $cadastrosRespRequest->merge([
                        'ano' => $ano,
                        'meses' => $meses,
                        'responsavel_id' => $responsavelId,
                    ]);
                    $payload['cadastros_mensais_responsavel'] = $this->extractResponseData($this->cadastrosMensaisPorResponsavel($cadastrosRespRequest)) ?? [];
                }

                if ($this->shouldInclude($include, 'area_opcao_detalhe') && $ano) {
                    $areaRequest = $request->duplicate();
                    $areaRequest->merge([
                        'ano' => $ano,
                        'limit' => $areaLimit,
                    ]);
                    $payload['area_opcao_detalhe'] = $this->extractResponseData($this->areaOpcaoDetalhe($areaRequest)) ?? [];
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
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar overview do dashboard: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar overview do dashboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Erro interno do servidor',
            ], 500);
        }
    }

    private function parseInclude(?string $raw): array
    {
        if (!$raw) {
            return [
                'cards',
                'anos_disponiveis',
                'status_chart',
                'cadastros_mensais',
                'top_cidades',
                'vgv_anual',
                'resumo',
                'cadastros_mensais_responsavel',
                'area_opcao_detalhe',
            ];
        }

        return collect(explode(',', $raw))
            ->map(fn($i) => trim($i))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function shouldInclude(array $include, string $key): bool
    {
        return in_array('*', $include, true) || in_array($key, $include, true);
    }

    private function extractResponsePayload(JsonResponse $response): array
    {
        $payload = $response->getData(true);
        return is_array($payload) ? $payload : [];
    }

    private function extractResponseData(JsonResponse $response): array|null
    {
        $payload = $this->extractResponsePayload($response);
        if (($payload['success'] ?? false) !== true) {
            return null;
        }

        return $payload['data'] ?? null;
    }
}
