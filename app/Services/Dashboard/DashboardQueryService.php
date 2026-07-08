<?php

namespace App\Services\Dashboard;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\ComitePendencia;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Contrato;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\LegalizacaoPendencia;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\Viabilidade;
use App\Support\Database\SqlDateParts;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardQueryService
{
    // -------------------------------------------------------------------------
    // Auxiliares de status
    // -------------------------------------------------------------------------

    public function negotiationStatuses(): array
    {
        return WorkflowStatus::negotiationActive();
    }

    public function signedDealStatuses(): array
    {
        return WorkflowStatus::signedAndLater();
    }

    public function workflowStatusLabel(?string $statusCode): string
    {
        if (! $statusCode) {
            return 'Sem Status';
        }

        return WorkflowStatus::tryFrom($statusCode)?->label() ?? $statusCode;
    }

    public function workflowStatusColor(?string $statusCode): string
    {
        return WorkflowStatus::tryFrom($statusCode)?->color() ?? '#94A3B8';
    }

    // -------------------------------------------------------------------------
    // Métodos de consulta (sem cache – o cache é gerenciado pelo controller)
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function cards(): array
    {
        $totalTerrenos = Terreno::count();

        $totalContratoAssinado = Terreno::where('workflow_status_code', WorkflowStatus::CONTRATO_ASSINADO->value)->count();

        $totalLegalizando = Terreno::where('workflow_status_code', WorkflowStatus::LEGALIZANDO->value)->count();

        $vgvContratoAssinado = Terreno::where('workflow_status_code', WorkflowStatus::CONTRATO_ASSINADO->value)
            ->join('terreno_produtos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
            ->whereNull('terrenos.deleted_at')
            ->sum(DB::raw('COALESCE(terreno_produtos.valor, 0) * COALESCE(terreno_produtos.unidades, 0)'));

        return [
            'total_terrenos' => $totalTerrenos,
            'total_contrato_assinado' => $totalContratoAssinado,
            'total_legalizando' => $totalLegalizando,
            'vgv_contrato_assinado' => (float) $vgvContratoAssinado,
        ];
    }

    /**
     * Payload executivo para a nova página de dashboard gerencial.
     *
     * @return array<string, mixed>
     */
    public function managementOverview(int $staleDays = 30, int $criticalDays = 15, int $limit = 8): array
    {
        $staleDays = max(1, min(120, $staleDays));
        $criticalDays = max(1, min(90, $criticalDays));
        $limit = max(1, min(25, $limit));

        $closureStatuses = WorkflowStatus::closure();
        $activeStatuses = array_values(array_diff(
            WorkflowStatus::values(),
            array_merge($closureStatuses, [WorkflowStatus::LEGALIZADO_FINALIZADO->value])
        ));
        $signedStatuses = $this->signedDealStatuses();
        $negotiationStatuses = $this->negotiationStatuses();

        $totalTerrenos = Terreno::count();
        $activeTerrenos = Terreno::whereIn('workflow_status_code', $activeStatuses)->count();
        $closedTerrenos = Terreno::whereIn('workflow_status_code', $signedStatuses)->count();
        $discardedTerrenos = Terreno::whereIn('workflow_status_code', $closureStatuses)->count();
        $staleTerrenos = Terreno::whereIn('workflow_status_code', $activeStatuses)
            ->where('updated_at', '<', now()->subDays($staleDays))
            ->count();

        $portfolio = $this->portfolioTotals($activeStatuses, $signedStatuses, $negotiationStatuses);
        $financial = $this->financialHealth();
        $funnel = $this->pipelineFunnel($staleDays);
        $alerts = $this->dashboardAlerts($staleDays, $criticalDays, $limit);
        $team = $this->teamPerformance($limit);
        $geography = $this->geography($limit);
        $operations = $this->operationalHealth($staleDays);

        $criticalTotal = collect($alerts)
            ->sum(fn (array $alert): int => (int) ($alert['count'] ?? 0));

        return [
            'generated_at' => now()->toIso8601String(),
            'parameters' => [
                'stale_days' => $staleDays,
                'critical_days' => $criticalDays,
                'limit' => $limit,
            ],
            'executive_summary' => [
                'total_terrenos' => $totalTerrenos,
                'active_terrenos' => $activeTerrenos,
                'closed_terrenos' => $closedTerrenos,
                'discarded_terrenos' => $discardedTerrenos,
                'stale_terrenos' => $staleTerrenos,
                'critical_alerts' => $criticalTotal,
                'conversion_rate' => $this->percent($closedTerrenos, $totalTerrenos),
                'active_rate' => $this->percent($activeTerrenos, $totalTerrenos),
                'vgv_pipeline' => $portfolio['vgv_pipeline'],
                'vgv_signed' => $portfolio['vgv_signed'],
                'vgv_weighted' => $portfolio['vgv_weighted'],
                'units_pipeline' => $portfolio['units_pipeline'],
                'units_signed' => $portfolio['units_signed'],
                'avg_ticket_signed' => $this->safeDivide($portfolio['vgv_signed'], $closedTerrenos),
                'profit_pipeline' => $financial['profit_pipeline'],
                'avg_margin_percent' => $financial['avg_margin_percent'],
            ],
            'funnel' => $funnel,
            'financial_health' => $financial,
            'operational_health' => $operations,
            'alerts' => array_values($alerts),
            'team_performance' => $team,
            'geography' => $geography,
        ];
    }

    /**
     * @return array{status_data: mixed, anos_disponiveis: array}
     */
    public function statusChart(?string $ano, ?string $dataInicio = null): array
    {
        $anosDisponiveis = Terreno::select(DB::raw('DISTINCT '.SqlDateParts::year('created_at').' as ano'))
            ->whereNotNull('created_at')
            ->orderBy('ano', 'desc')
            ->pluck('ano')
            ->toArray();

        $query = Terreno::select('workflow_status_code', DB::raw('COUNT(*) as total'));

        if ($dataInicio) {
            $query->where('created_at', '>=', Carbon::parse($dataInicio)->startOfDay());
        } elseif ($ano) {
            $query->whereYear('created_at', $ano);
        }

        $statusData = $query->groupBy('workflow_status_code')
            ->get()
            ->map(fn ($item) => [
                'status_code' => $item->workflow_status_code,
                'status_nome' => $this->workflowStatusLabel($item->workflow_status_code),
                'status_cor' => $this->workflowStatusColor($item->workflow_status_code),
                'total' => $item->total,
            ]);

        return [
            'status_data' => $statusData,
            'anos_disponiveis' => $anosDisponiveis,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cadastrosMensais(?string $ano, int $meses, ?string $dataInicio, ?string $dataFim): array
    {
        $query = Terreno::select(
            DB::raw(SqlDateParts::yearAs('created_at', 'ano')),
            DB::raw(SqlDateParts::monthAs('created_at', 'mes')),
            DB::raw('COUNT(*) as total')
        );

        if ($ano) {
            $query->whereYear('created_at', $ano);
        } elseif ($dataInicio && $dataFim) {
            $query->whereBetween('created_at', [
                Carbon::parse($dataInicio)->startOfDay(),
                Carbon::parse($dataFim)->endOfDay(),
            ]);
        } else {
            $query->where('created_at', '>=', Carbon::now()->subMonths($meses)->startOfMonth());
        }

        $cadastros = $query
            ->groupBy(DB::raw(SqlDateParts::year('created_at')), DB::raw(SqlDateParts::month('created_at')))
            ->orderBy('ano', 'asc')
            ->orderBy('mes', 'asc')
            ->get()
            ->map(fn ($item) => [
                'ano' => $item->ano,
                'mes' => $item->mes,
                'mes_nome' => Carbon::create($item->ano, $item->mes)->translatedFormat('F'),
                'periodo' => Carbon::create($item->ano, $item->mes)->format('Y-m'),
                'total' => $item->total,
            ]);

        return [
            'cadastros' => $cadastros,
            'filters' => [
                'ano' => $ano ?? null,
                'meses' => $ano ? null : $meses,
            ],
        ];
    }

    /**
     * @return mixed
     */
    public function terrenosPorResponsavel(string $filtro, ?string $ano, ?string $mes, ?int $limit)
    {
        $query = Terreno::select('responsavel_id', DB::raw('COUNT(*) as total'))
            ->with('responsavel:id,name,email')
            ->whereNotNull('responsavel_id');

        if ($filtro === 'ano' && $ano) {
            $query->whereYear('created_at', $ano);
        } elseif ($filtro === 'mes' && $ano && $mes) {
            $query->whereYear('created_at', $ano)->whereMonth('created_at', $mes);
        }

        $query->groupBy('responsavel_id')->orderByDesc('total');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(fn ($item) => [
            'responsavel_id' => $item->responsavel_id,
            'responsavel_nome' => $item->responsavel?->name ?? 'Não informado',
            'responsavel_email' => $item->responsavel?->email ?? null,
            'total' => $item->total,
        ]);
    }

    /**
     * @return mixed
     */
    public function topCidades(string $filtro, ?string $ano, ?string $mes, int $limit)
    {
        $query = Terreno::select('cidade_code', DB::raw('COUNT(*) as total'))
            ->with('cidade:code,city,state_code')
            ->whereNotNull('cidade_code')
            ->where('cidade_code', '!=', '');

        if ($filtro === 'ano' && $ano) {
            $query->whereYear('created_at', $ano);
        } elseif ($filtro === 'mes' && $ano && $mes) {
            $query->whereYear('created_at', $ano)->whereMonth('created_at', $mes);
        }

        return $query
            ->groupBy('cidade_code')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($item, $index) => [
                'posicao' => $index + 1,
                'cidade_code' => $item->cidade_code,
                'cidade' => $item->cidade?->city,
                'estado' => $item->cidade?->state_code,
                'total' => $item->total,
            ]);
    }

    /**
     * @return mixed
     */
    public function vgvAnual()
    {
        return Terreno::leftJoin('terreno_produtos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
            ->whereNotNull('terrenos.data_opcao')
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
            ->map(fn ($item) => [
                'ano' => $item->ano,
                'vgv_total' => (float) $item->vgv_total,
                'vgv_formatado' => 'R$ '.number_format($item->vgv_total, 2, ',', '.'),
                'total_unidades' => (int) $item->total_unidades,
                'total_terrenos' => $item->total_terrenos,
                'total_areas' => $item->total_terrenos,
            ]);
    }

    /**
     * @return mixed
     */
    public function unidadesFechadasAnual()
    {
        return TerrenoProduto::join('terrenos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
            ->whereIn('terrenos.workflow_status_code', $this->signedDealStatuses())
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
            ->map(fn ($item) => [
                'ano' => $item->ano,
                'total_unidades' => (int) $item->total_unidades,
                'total_terrenos' => $item->total_terrenos,
                'total_areas' => $item->total_terrenos,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function cadastrosMensaisPorResponsavel(
        ?string $ano,
        int $meses,
        ?string $dataInicio,
        ?string $dataFim,
        ?string $responsavelId,
    ): mixed {
        $query = Terreno::select(
            'responsavel_id',
            DB::raw(SqlDateParts::yearAs('created_at', 'ano')),
            DB::raw(SqlDateParts::monthAs('created_at', 'mes')),
            DB::raw('COUNT(*) as total')
        )
            ->with('responsavel:id,name')
            ->whereNotNull('responsavel_id');

        if ($responsavelId) {
            $query->where('responsavel_id', $responsavelId);
        }

        if ($ano) {
            $query->whereYear('created_at', $ano);
        } elseif ($dataInicio && $dataFim) {
            $query->whereBetween('created_at', [
                Carbon::parse($dataInicio)->startOfDay(),
                Carbon::parse($dataFim)->endOfDay(),
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
            ->map(fn ($item) => [
                'responsavel_id' => $item->responsavel_id,
                'responsavel_nome' => $item->responsavel?->name ?? 'Não informado',
                'ano' => $item->ano,
                'mes' => $item->mes,
                'mes_nome' => Carbon::create($item->ano, $item->mes)->translatedFormat('F'),
                'periodo' => Carbon::create($item->ano, $item->mes)->format('Y-m'),
                'total' => $item->total,
            ]);

        return $cadastros->groupBy('responsavel_id')->map(function ($items, $responsavelId) {
            $primeiro = $items->first();

            return [
                'responsavel_id' => $responsavelId,
                'responsavel_nome' => $primeiro['responsavel_nome'],
                'total_geral' => $items->sum('total'),
                'mensal' => $items->map(fn ($item) => [
                    'ano' => $item['ano'],
                    'mes' => $item['mes'],
                    'mes_nome' => $item['mes_nome'],
                    'periodo' => $item['periodo'],
                    'total' => $item['total'],
                ])->values(),
            ];
        })->sortByDesc('total_geral')->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function resumoGeral(): array
    {
        $totalTerrenos = Terreno::count();

        $totalOpcao = Terreno::whereIn('workflow_status_code', $this->negotiationStatuses())->count();

        $totalFechados = Terreno::whereIn('workflow_status_code', $this->signedDealStatuses())->count();

        $totalUnidadesOpcao = TerrenoProduto::whereHas(
            'terreno',
            fn ($q) => $q->whereIn('workflow_status_code', $this->negotiationStatuses())
        )->sum('unidades');

        $totalUnidadesFechadas = TerrenoProduto::whereHas(
            'terreno',
            fn ($q) => $q->whereIn('workflow_status_code', $this->signedDealStatuses())
        )->sum('unidades');

        $cadastrosMesAtual = Terreno::whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();

        $topResponsaveis = Terreno::select('responsavel_id', DB::raw('COUNT(*) as total'))
            ->with('responsavel:id,name')
            ->whereNotNull('responsavel_id')
            ->groupBy('responsavel_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'nome' => $item->responsavel?->name ?? 'Não informado',
                'total' => $item->total,
            ]);

        $distribuicaoStatus = Terreno::select('workflow_status_code', DB::raw('COUNT(*) as total'))
            ->groupBy('workflow_status_code')
            ->get()
            ->map(fn ($item) => [
                'status' => $this->workflowStatusLabel($item->workflow_status_code),
                'cor' => $this->workflowStatusColor($item->workflow_status_code),
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
    }

    /**
     * @return array<int, int|string>
     */
    public function anosDisponiveis(): array
    {
        return Terreno::select(DB::raw('DISTINCT '.SqlDateParts::year('created_at').' as ano'))
            ->whereNotNull('created_at')
            ->orderBy('ano', 'desc')
            ->pluck('ano')
            ->toArray();
    }

    /**
     * @return mixed
     */
    public function areaOpcaoDetalhe(string $ano, ?int $limit)
    {
        $query = Terreno::query()
            ->whereYear('data_opcao', $ano)
            ->with(['cidade', 'responsavel'])
            ->withSum('terrenoProdutos as total_unidades', 'unidades')
            ->addSelect([
                'vgv_total' => TerrenoProduto::select(DB::raw('SUM(COALESCE(valor, 0) * COALESCE(unidades, 0))'))
                    ->whereColumn('terreno_id', 'terrenos.id'),
            ]);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->orderByDesc('vgv_total')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'nome' => $item->nome,
                'cidade' => $item->cidade?->city,
                'estado' => $item->cidade?->state_code,
                'responsavel' => $item->responsavel?->name,
                'total_unidades' => (int) $item->total_unidades,
                'vgv_total' => (float) $item->vgv_total,
                'vgv_formatado' => 'R$ '.number_format($item->vgv_total, 2, ',', '.'),
            ]);
    }

    /**
     * @param  array<int, string>  $activeStatuses
     * @param  array<int, string>  $signedStatuses
     * @param  array<int, string>  $negotiationStatuses
     * @return array<string, float|int>
     */
    private function portfolioTotals(array $activeStatuses, array $signedStatuses, array $negotiationStatuses): array
    {
        $pipeline = $this->sumProductsForStatuses($activeStatuses);
        $signed = $this->sumProductsForStatuses($signedStatuses);
        $negotiation = $this->sumProductsForStatuses($negotiationStatuses);

        return [
            'vgv_pipeline' => $pipeline['vgv'],
            'vgv_signed' => $signed['vgv'],
            'vgv_negotiation' => $negotiation['vgv'],
            'vgv_weighted' => $this->weightedVgvByStatus(),
            'units_pipeline' => $pipeline['units'],
            'units_signed' => $signed['units'],
            'units_negotiation' => $negotiation['units'],
        ];
    }

    /**
     * @param  array<int, string>  $statuses
     * @return array{vgv: float, units: int}
     */
    private function sumProductsForStatuses(array $statuses): array
    {
        if ($statuses === []) {
            return ['vgv' => 0.0, 'units' => 0];
        }

        $row = TerrenoProduto::join('terrenos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
            ->whereIn('terrenos.workflow_status_code', $statuses)
            ->whereNull('terrenos.deleted_at')
            ->selectRaw('COALESCE(SUM(COALESCE(terreno_produtos.valor, 0) * COALESCE(terreno_produtos.unidades, 0)), 0) as vgv')
            ->selectRaw('COALESCE(SUM(COALESCE(terreno_produtos.unidades, 0)), 0) as units')
            ->first();

        return [
            'vgv' => (float) ($row?->vgv ?? 0),
            'units' => (int) ($row?->units ?? 0),
        ];
    }

    private function weightedVgvByStatus(): float
    {
        $weights = [
            WorkflowStatus::EM_ANALISE->value => 0.1,
            WorkflowStatus::AGUARDANDO_VIABILIDADE->value => 0.2,
            WorkflowStatus::VIABILIDADE_APROVADA->value => 0.45,
            WorkflowStatus::AGUARDANDO_COMITE->value => 0.55,
            WorkflowStatus::NEGOCIACAO_MINUTA->value => 0.7,
            WorkflowStatus::CONTRATO_ASSINADO->value => 1.0,
            WorkflowStatus::LEGALIZANDO->value => 1.0,
            WorkflowStatus::LEGALIZADO_FINALIZADO->value => 1.0,
        ];

        return TerrenoProduto::join('terrenos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
            ->whereNull('terrenos.deleted_at')
            ->select('terrenos.workflow_status_code')
            ->selectRaw('SUM(COALESCE(terreno_produtos.valor, 0) * COALESCE(terreno_produtos.unidades, 0)) as vgv')
            ->groupBy('terrenos.workflow_status_code')
            ->get()
            ->sum(fn ($item): float => ((float) $item->vgv) * ($weights[$item->workflow_status_code] ?? 0.0));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pipelineFunnel(int $staleDays): array
    {
        $counts = Terreno::select('workflow_status_code', DB::raw('COUNT(*) as total'))
            ->groupBy('workflow_status_code')
            ->pluck('total', 'workflow_status_code');

        return collect(WorkflowStatus::cases())->map(function (WorkflowStatus $status, int $index) use ($counts, $staleDays) {
            $currentCount = (int) ($counts[$status->value] ?? 0);
            $nextStatus = WorkflowStatus::cases()[$index + 1] ?? null;
            $nextCount = $nextStatus ? (int) ($counts[$nextStatus->value] ?? 0) : null;
            $products = $this->sumProductsForStatuses([$status->value]);

            return [
                'status_code' => $status->value,
                'status_nome' => $status->label(),
                'status_cor' => $status->color(),
                'stage' => $status->stage(),
                'total' => $currentCount,
                'vgv_total' => $products['vgv'],
                'total_unidades' => $products['units'],
                'stale_count' => Terreno::where('workflow_status_code', $status->value)
                    ->where('updated_at', '<', now()->subDays($staleDays))
                    ->count(),
                'avg_days_in_status' => $this->avgDaysInStatus($status->value),
                'conversion_to_next' => $nextCount === null ? null : $this->percent($nextCount, $currentCount),
            ];
        })->values()->all();
    }

    private function avgDaysInStatus(string $statusCode): ?float
    {
        $items = Terreno::where('workflow_status_code', $statusCode)
            ->select('workflow_status_changed_at', 'created_at')
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        return round((float) $items->avg(function (Terreno $terreno): int {
            $baseDate = $terreno->workflow_status_changed_at ?? $terreno->created_at;

            return $baseDate ? (int) $baseDate->diffInDays(now()) : 0;
        }), 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function financialHealth(): array
    {
        $viabilidades = Viabilidade::where('is_current', true)
            ->get(['id', 'terreno_id', 'status', 'approval_status', 'resultados_dre']);

        $withDre = $viabilidades->filter(fn (Viabilidade $viabilidade): bool => is_array($viabilidade->resultados_dre));
        $vgvPipeline = 0.0;
        $profitPipeline = 0.0;
        $marginValues = [];
        $roiValues = [];

        foreach ($withDre as $viabilidade) {
            $indicadores = $viabilidade->resultados_dre['indicadores'] ?? [];
            $vgvPipeline += (float) ($indicadores['vgv_total'] ?? 0);
            $profitPipeline += (float) ($indicadores['lucro_liquido'] ?? ($viabilidade->resultados_dre['lucro_liquido_projeto'] ?? 0));

            if (isset($indicadores['margem_liquida_percentual'])) {
                $marginValues[] = (float) $indicadores['margem_liquida_percentual'];
            }

            if (isset($indicadores['roi_percentual'])) {
                $roiValues[] = (float) $indicadores['roi_percentual'];
            }
        }

        $approvalPending = $viabilidades
            ->where('approval_status', 'pendente')
            ->count();

        return [
            'current_viabilities' => $viabilidades->count(),
            'with_dre' => $withDre->count(),
            'missing_dre' => max(0, $viabilidades->count() - $withDre->count()),
            'approval_pending' => $approvalPending,
            'vgv_pipeline' => round($vgvPipeline, 2),
            'profit_pipeline' => round($profitPipeline, 2),
            'avg_margin_percent' => $marginValues === [] ? 0.0 : round(array_sum($marginValues) / count($marginValues), 2),
            'avg_roi_percent' => $roiValues === [] ? 0.0 : round(array_sum($roiValues) / count($roiValues), 2),
            'by_status' => $viabilidades
                ->groupBy(fn (Viabilidade $viabilidade): string => $viabilidade->status ?? 'sem_status')
                ->map(fn ($items, string $status): array => [
                    'status' => $status,
                    'total' => $items->count(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function dashboardAlerts(int $staleDays, int $criticalDays, int $limit): array
    {
        return [
            'stale_terrenos' => [
                'key' => 'stale_terrenos',
                'label' => "Terrenos sem atualização há {$staleDays}+ dias",
                'count' => Terreno::where('updated_at', '<', now()->subDays($staleDays))->count(),
                'severity' => 'warning',
                'items' => $this->recentTerrenos(
                    Terreno::where('updated_at', '<', now()->subDays($staleDays)),
                    $limit
                ),
            ],
            'missing_data' => [
                'key' => 'missing_data',
                'label' => 'Terrenos com cadastro incompleto',
                'count' => Terreno::where(function ($query) {
                    $query->whereNull('responsavel_id')
                        ->orWhereNull('cidade_code')
                        ->orWhereNull('area_calculada');
                })->count(),
                'severity' => 'info',
                'items' => $this->recentTerrenos(
                    Terreno::where(function ($query) {
                        $query->whereNull('responsavel_id')
                            ->orWhereNull('cidade_code')
                            ->orWhereNull('area_calculada');
                    }),
                    $limit
                ),
            ],
            'viability_pending' => [
                'key' => 'viability_pending',
                'label' => 'Viabilidades aguardando aprovação',
                'count' => Viabilidade::where('is_current', true)
                    ->where('approval_status', 'pendente')
                    ->whereNotNull('approval_requested_at')
                    ->count(),
                'severity' => 'warning',
                'items' => [],
            ],
            'committee_open_issues' => [
                'key' => 'committee_open_issues',
                'label' => 'Pendências abertas de comitê',
                'count' => ComitePendencia::where('status', 'open')->count(),
                'severity' => 'danger',
                'items' => ComitePendencia::where('status', 'open')
                    ->orderByRaw('due_date is null, due_date asc')
                    ->limit($limit)
                    ->get(['id', 'terreno_id', 'title', 'severity', 'due_date'])
                    ->map(fn (ComitePendencia $item): array => [
                        'id' => $item->id,
                        'terreno_id' => $item->terreno_id,
                        'title' => $item->title,
                        'severity' => $item->severity,
                        'due_date' => $item->due_date?->format('Y-m-d'),
                    ])
                    ->all(),
            ],
            'legalization_critical' => [
                'key' => 'legalization_critical',
                'label' => 'Pendências críticas de legalização',
                'count' => LegalizacaoPendencia::whereNull('resolved_at')
                    ->where(function ($query) use ($criticalDays) {
                        $query->where('is_critical', true)
                            ->orWhere('due_date', '<=', now()->addDays($criticalDays)->toDateString());
                    })
                    ->count(),
                'severity' => 'danger',
                'items' => LegalizacaoPendencia::whereNull('resolved_at')
                    ->where(function ($query) use ($criticalDays) {
                        $query->where('is_critical', true)
                            ->orWhere('due_date', '<=', now()->addDays($criticalDays)->toDateString());
                    })
                    ->orderByRaw('due_date is null, due_date asc')
                    ->limit($limit)
                    ->get(['id', 'legalizacao_id', 'title', 'severity', 'is_critical', 'due_date'])
                    ->map(fn (LegalizacaoPendencia $item): array => [
                        'id' => $item->id,
                        'legalizacao_id' => $item->legalizacao_id,
                        'title' => $item->title,
                        'severity' => $item->severity,
                        'is_critical' => (bool) $item->is_critical,
                        'due_date' => $item->due_date?->format('Y-m-d'),
                    ])
                    ->all(),
            ],
            'overdue_tasks' => [
                'key' => 'overdue_tasks',
                'label' => 'Tarefas vencidas',
                'count' => Task::whereNull('completed_at')
                    ->whereNotIn('status', ['done', 'concluida', 'concluído', 'completed'])
                    ->whereDate('due_date', '<', today())
                    ->count(),
                'severity' => 'warning',
                'items' => Task::whereNull('completed_at')
                    ->whereNotIn('status', ['done', 'concluida', 'concluído', 'completed'])
                    ->whereDate('due_date', '<', today())
                    ->with('assignedUser:id,name')
                    ->orderBy('due_date')
                    ->limit($limit)
                    ->get(['id', 'terreno_id', 'title', 'priority', 'due_date', 'assigned_to'])
                    ->map(fn (Task $task): array => [
                        'id' => $task->id,
                        'terreno_id' => $task->terreno_id,
                        'title' => $task->title,
                        'priority' => $task->priority,
                        'due_date' => $task->due_date?->format('Y-m-d'),
                        'assigned_to_name' => $task->assignedUser?->name,
                    ])
                    ->all(),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentTerrenos($query, int $limit): array
    {
        return $query
            ->with(['responsavel:id,name', 'cidade:code,city,state_code'])
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'nome', 'responsavel_id', 'cidade_code', 'workflow_status_code', 'updated_at'])
            ->map(fn (Terreno $terreno): array => [
                'id' => $terreno->id,
                'nome' => $terreno->nome,
                'status_code' => $terreno->workflow_status_code,
                'status_nome' => $this->workflowStatusLabel($terreno->workflow_status_code),
                'responsavel_nome' => $terreno->responsavel?->name,
                'cidade' => $terreno->cidade?->city,
                'estado' => $terreno->cidade?->state_code,
                'updated_at' => $terreno->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function operationalHealth(int $staleDays): array
    {
        $activeNegotiations = Negociacao::whereNull('closed_at')->count();
        $staleNegotiations = Negociacao::whereNull('closed_at')
            ->where('updated_at', '<', now()->subDays($staleDays))
            ->count();
        $committeeOpen = ComiteRevisao::whereNull('decided_at')->count();
        $legalizationsActive = Legalizacao::whereIn('status', ['planejado', 'em_andamento'])->count();
        $legalizationProgress = Legalizacao::whereIn('status', ['planejado', 'em_andamento'])->avg('percentual_concluido');
        $legalizationDelayedStages = LegalizacaoEtapa::whereIn('status', ['atrasada', 'bloqueada'])
            ->orWhere(function ($query) {
                $query->where('status', '!=', 'concluida')
                    ->whereDate('fim_planejado', '<', today());
            })
            ->count();
        $openProjects = Projeto::whereNotIn('status', ['finalizado', 'cancelado'])->count();

        return [
            'active_negotiations' => $activeNegotiations,
            'stale_negotiations' => $staleNegotiations,
            'proposal_value_active' => (float) Negociacao::whereNull('closed_at')->sum('proposal_value'),
            'signed_contracts' => Contrato::whereNotNull('signed_at')->count(),
            'committee_open' => $committeeOpen,
            'committee_open_issues' => ComitePendencia::where('status', 'open')->count(),
            'legalizations_active' => $legalizationsActive,
            'legalization_avg_progress' => round((float) ($legalizationProgress ?? 0), 1),
            'legalization_delayed_stages' => $legalizationDelayedStages,
            'legalization_critical_issues' => LegalizacaoPendencia::whereNull('resolved_at')->where('is_critical', true)->count(),
            'open_projects' => $openProjects,
            'projects_ready_to_register' => Projeto::whereNotNull('pronto_para_registro_em')->count(),
            'open_tasks' => Task::whereNull('completed_at')->count(),
            'overdue_tasks' => Task::whereNull('completed_at')->whereDate('due_date', '<', today())->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function teamPerformance(int $limit): array
    {
        return Terreno::select('responsavel_id', DB::raw('COUNT(*) as total'))
            ->selectRaw('SUM(CASE WHEN workflow_status_code = ? THEN 1 ELSE 0 END) as contratos', [WorkflowStatus::CONTRATO_ASSINADO->value])
            ->selectRaw('SUM(CASE WHEN workflow_status_code IN (?, ?) THEN 1 ELSE 0 END) as encerrados', WorkflowStatus::closure())
            ->with('responsavel:id,name,email')
            ->whereNotNull('responsavel_id')
            ->groupBy('responsavel_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($item): array => [
                'responsavel_id' => $item->responsavel_id,
                'responsavel_nome' => $item->responsavel?->name ?? 'Não informado',
                'responsavel_email' => $item->responsavel?->email,
                'total' => (int) $item->total,
                'contratos' => (int) $item->contratos,
                'encerrados' => (int) $item->encerrados,
                'conversion_rate' => $this->percent((int) $item->contratos, (int) $item->total),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function geography(int $limit): array
    {
        return Terreno::select('cidade_code', DB::raw('COUNT(*) as total'))
            ->with('cidade:code,city,state_code')
            ->whereNotNull('cidade_code')
            ->where('cidade_code', '!=', '')
            ->groupBy('cidade_code')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(function ($item, int $index): array {
                $products = TerrenoProduto::join('terrenos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
                    ->where('terrenos.cidade_code', $item->cidade_code)
                    ->whereNull('terrenos.deleted_at')
                    ->selectRaw('COALESCE(SUM(COALESCE(terreno_produtos.valor, 0) * COALESCE(terreno_produtos.unidades, 0)), 0) as vgv')
                    ->selectRaw('COALESCE(SUM(COALESCE(terreno_produtos.unidades, 0)), 0) as units')
                    ->first();

                return [
                    'posicao' => $index + 1,
                    'cidade_code' => $item->cidade_code,
                    'cidade' => $item->cidade?->city,
                    'estado' => $item->cidade?->state_code,
                    'total' => (int) $item->total,
                    'vgv_total' => (float) ($products?->vgv ?? 0),
                    'total_unidades' => (int) ($products?->units ?? 0),
                ];
            })
            ->all();
    }

    private function percent(int|float $part, int|float $total): float
    {
        if ((float) $total === 0.0) {
            return 0.0;
        }

        return round(((float) $part / (float) $total) * 100, 1);
    }

    private function safeDivide(int|float $value, int|float $divisor): float
    {
        if ((float) $divisor === 0.0) {
            return 0.0;
        }

        return round((float) $value / (float) $divisor, 2);
    }

    /**
     * Monta o payload agregado do overview, incluindo apenas as seções pedidas.
     *
     * @param  array<int, string>  $include
     * @return array<string, mixed>
     */
    public function buildOverview(
        array $include,
        ?string $ano,
        ?string $mes,
        int $meses,
        int $topLimit,
        int $areaLimit,
        ?string $responsavelId,
    ): array {
        $payload = [];

        if ($this->shouldInclude($include, 'cards')) {
            $payload['cards'] = $this->cards();
        }

        if ($this->shouldInclude($include, 'status_chart') || $this->shouldInclude($include, 'anos_disponiveis')) {
            $statusData = $this->statusChart($ano);
            if ($this->shouldInclude($include, 'status_chart')) {
                $payload['status_chart'] = $statusData['status_data'];
            }
            if ($this->shouldInclude($include, 'anos_disponiveis')) {
                $payload['anos_disponiveis'] = $statusData['anos_disponiveis'];
            }
        }

        if ($this->shouldInclude($include, 'cadastros_mensais')) {
            $payload['cadastros_mensais'] = $this->cadastrosMensais(
                ano: $ano, meses: $meses, dataInicio: null, dataFim: null
            )['cadastros'];
        }

        if ($this->shouldInclude($include, 'top_cidades')) {
            $filtro = ($ano && $mes) ? 'mes' : ($ano ? 'ano' : 'geral');
            $payload['top_cidades'] = $this->topCidades(
                filtro: $filtro, ano: $ano, mes: $mes, limit: $topLimit
            );
        }

        if ($this->shouldInclude($include, 'vgv_anual')) {
            $payload['vgv_anual'] = $this->vgvAnual();
        }

        if ($this->shouldInclude($include, 'unidades_fechadas_anual')) {
            $payload['unidades_fechadas_anual'] = $this->unidadesFechadasAnual();
        }

        if ($this->shouldInclude($include, 'resumo')) {
            $payload['resumo'] = $this->resumoGeral();
        }

        if ($this->shouldInclude($include, 'cadastros_mensais_responsavel')) {
            $payload['cadastros_mensais_responsavel'] = $this->cadastrosMensaisPorResponsavel(
                ano: $ano, meses: $meses, dataInicio: null, dataFim: null, responsavelId: $responsavelId
            );
        }

        if ($this->shouldInclude($include, 'area_opcao_detalhe') && $ano) {
            $payload['area_opcao_detalhe'] = $this->areaOpcaoDetalhe(ano: $ano, limit: $areaLimit);
        } elseif ($this->shouldInclude($include, 'area_opcao_detalhe')) {
            $payload['area_opcao_detalhe'] = [];
        }

        return $payload;
    }

    /**
     * @param  array<int, string>  $include
     */
    private function shouldInclude(array $include, string $key): bool
    {
        return in_array('*', $include, true) || in_array($key, $include, true);
    }
}
