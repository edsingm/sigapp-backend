<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\ComitePendencia;
use App\Models\Tenant\LegalizacaoPendencia;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use App\Repositories\Tenant\DashboardRepository;
use Carbon\Carbon;

class DashboardQueryService
{
    public function __construct(private readonly DashboardRepository $repository) {}

    /** @return array<int, string> */
    public function negotiationStatuses(): array
    {
        return WorkflowStatus::negotiationActive();
    }

    /** @return array<int, string> */
    public function signedDealStatuses(): array
    {
        return WorkflowStatus::signedAndLater();
    }

    public function workflowStatusLabel(?string $statusCode): string
    {
        return $statusCode ? (WorkflowStatus::tryFrom($statusCode)?->label() ?? $statusCode) : 'Sem Status';
    }

    public function workflowStatusColor(?string $statusCode): string
    {
        return WorkflowStatus::tryFrom((string) $statusCode)?->color() ?? '#94A3B8';
    }

    /** @return array<string, mixed> */
    public function cards(): array
    {
        return $this->repository->cardTotals();
    }

    /** @return array<string, mixed> */
    public function managementOverview(int $staleDays = 30, int $criticalDays = 15, int $limit = 8): array
    {
        $staleDays = max(1, min(120, $staleDays));
        $criticalDays = max(1, min(90, $criticalDays));
        $limit = max(1, min(25, $limit));
        $closure = WorkflowStatus::closure();
        $active = array_values(array_diff(WorkflowStatus::values(), [...$closure, WorkflowStatus::LEGALIZADO_FINALIZADO->value]));
        $signed = $this->signedDealStatuses();
        $negotiation = $this->negotiationStatuses();
        $total = $this->repository->totalTerrenos();
        $activeTotal = $this->repository->terrenoCountForStatuses($active);
        $closedTotal = $this->repository->terrenoCountForStatuses($signed);
        $portfolio = $this->portfolioTotals($active, $signed, $negotiation);
        $financial = $this->financialHealth();
        $alerts = $this->dashboardAlerts($staleDays, $criticalDays, $limit);

        return [
            'generated_at' => now()->toIso8601String(),
            'parameters' => ['stale_days' => $staleDays, 'critical_days' => $criticalDays, 'limit' => $limit],
            'executive_summary' => [
                'total_terrenos' => $total,
                'active_terrenos' => $activeTotal,
                'closed_terrenos' => $closedTotal,
                'discarded_terrenos' => $this->repository->terrenoCountForStatuses($closure),
                'stale_terrenos' => $this->repository->staleTerrenoCountForStatuses($active, $staleDays),
                'critical_alerts' => collect($alerts)->sum(fn (array $alert): int => (int) $alert['count']),
                'conversion_rate' => $this->percent($closedTotal, $total),
                'active_rate' => $this->percent($activeTotal, $total),
                'vgv_pipeline' => $portfolio['vgv_pipeline'],
                'vgv_signed' => $portfolio['vgv_signed'],
                'vgv_weighted' => $portfolio['vgv_weighted'],
                'units_pipeline' => $portfolio['units_pipeline'],
                'units_signed' => $portfolio['units_signed'],
                'avg_ticket_signed' => $this->safeDivide($portfolio['vgv_signed'], $closedTotal),
                'profit_pipeline' => $financial['profit_pipeline'],
                'avg_margin_percent' => $financial['avg_margin_percent'],
            ],
            'funnel' => $this->pipelineFunnel($staleDays),
            'financial_health' => $financial,
            'operational_health' => $this->operationalHealth($staleDays),
            'alerts' => array_values($alerts),
            'team_performance' => $this->teamPerformance($limit),
            'geography' => $this->geography($limit),
        ];
    }

    /** @return array{status_data: mixed, anos_disponiveis: array<mixed>} */
    public function statusChart(?string $ano, ?string $dataInicio = null): array
    {
        $rows = $this->repository->statusCounts($dataInicio ? Carbon::parse($dataInicio)->startOfDay() : null, $dataInicio ? null : ($ano ?: null));

        return [
            'status_data' => $rows->map(fn ($row): array => [
                'status_code' => $row->workflow_status_code,
                'status_nome' => $this->workflowStatusLabel($row->workflow_status_code),
                'status_cor' => $this->workflowStatusColor($row->workflow_status_code),
                'total' => $row->total,
            ]),
            'anos_disponiveis' => $this->repository->availableYears(),
        ];
    }

    /** @return array<string, mixed> */
    public function cadastrosMensais(?string $ano, int $meses, ?string $dataInicio, ?string $dataFim): array
    {
        $hasRange = $dataInicio !== null && $dataFim !== null;
        $from = $hasRange ? Carbon::parse($dataInicio)->startOfDay() : ($ano ? null : now()->subMonths($meses)->startOfMonth());
        $to = $hasRange ? Carbon::parse($dataFim)->endOfDay() : null;
        $rows = $this->repository->monthlyRegistrationCounts($ano ?: null, $from, $to)->map(fn ($row): array => $this->monthlyPayload($row));

        return ['cadastros' => $rows, 'filters' => ['ano' => $ano, 'meses' => $ano ? null : $meses]];
    }

    public function terrenosPorResponsavel(string $filtro, ?string $ano, ?string $mes, ?int $limit): mixed
    {
        return $this->repository->responsibleCounts($filtro, $ano, $mes, $limit)->map(fn ($row): array => [
            'responsavel_id' => $row->responsavel_id,
            'responsavel_nome' => $row->responsavel?->name ?? 'Não informado',
            'responsavel_email' => $row->responsavel?->email,
            'total' => $row->total,
        ]);
    }

    public function topCidades(string $filtro, ?string $ano, ?string $mes, int $limit): mixed
    {
        return $this->repository->cityCounts($filtro, $ano, $mes, $limit)->map(fn ($row, int $index): array => [
            'posicao' => $index + 1, 'cidade_code' => $row->cidade_code, 'cidade' => $row->cidade?->city,
            'estado' => $row->cidade?->state_code, 'total' => $row->total,
        ]);
    }

    public function vgvAnual(): mixed
    {
        return $this->repository->annualVgvRows()->map(fn ($row): array => [
            'ano' => $row->ano, 'vgv_total' => (float) $row->vgv_total,
            'vgv_formatado' => 'R$ '.number_format((float) $row->vgv_total, 2, ',', '.'),
            'total_unidades' => (int) $row->total_unidades, 'total_terrenos' => $row->total_terrenos, 'total_areas' => $row->total_terrenos,
        ]);
    }

    public function unidadesFechadasAnual(): mixed
    {
        return $this->repository->annualClosedUnitRows($this->signedDealStatuses())->map(fn ($row): array => [
            'ano' => $row->ano, 'total_unidades' => (int) $row->total_unidades,
            'total_terrenos' => $row->total_terrenos, 'total_areas' => $row->total_terrenos,
        ]);
    }

    public function cadastrosMensaisPorResponsavel(?string $ano, int $meses, ?string $dataInicio, ?string $dataFim, ?string $responsavelId): mixed
    {
        $hasRange = $dataInicio !== null && $dataFim !== null;
        $from = $hasRange ? Carbon::parse($dataInicio)->startOfDay() : ($ano ? null : now()->subMonths($meses)->startOfMonth());
        $to = $hasRange ? Carbon::parse($dataFim)->endOfDay() : null;
        $rows = $this->repository->monthlyResponsibleRows($ano ?: null, $from, $to, $responsavelId)->map(function ($row): array {
            return ['responsavel_id' => $row->responsavel_id, 'responsavel_nome' => $row->responsavel?->name ?? 'Não informado', ...$this->monthlyPayload($row)];
        });

        return $rows->groupBy('responsavel_id')->map(function ($items, $id): array {
            $first = $items->first();

            return [
                'responsavel_id' => $id, 'responsavel_nome' => is_array($first) ? ($first['responsavel_nome'] ?? 'Não informado') : 'Não informado', 'total_geral' => $items->sum('total'),
                'mensal' => $items->map(fn (array $item): array => collect($item)->only(['ano', 'mes', 'mes_nome', 'periodo', 'total'])->all())->values(),
            ];
        })->sortByDesc('total_geral')->values();
    }

    /** @return array<string, mixed> */
    public function resumoGeral(): array
    {
        $negotiation = $this->negotiationStatuses();
        $signed = $this->signedDealStatuses();

        return [
            'totais' => [
                'terrenos' => $this->repository->totalTerrenos(),
                'opcao' => $this->repository->terrenoCountForStatuses($negotiation),
                'fechados' => $this->repository->terrenoCountForStatuses($signed),
                'unidades_opcao' => $this->repository->unitsForStatuses($negotiation),
                'unidades_fechadas' => $this->repository->unitsForStatuses($signed),
                'cadastros_mes_atual' => $this->repository->currentMonthTerrenoCount(),
            ],
            'top_responsaveis' => $this->repository->responsibleCounts('geral', null, null, 5)->map(fn ($row): array => ['nome' => $row->responsavel?->name ?? 'Não informado', 'total' => $row->total]),
            'distribuicao_status' => $this->repository->statusCounts(null, null)->map(fn ($row): array => ['status' => $this->workflowStatusLabel($row->workflow_status_code), 'cor' => $this->workflowStatusColor($row->workflow_status_code), 'total' => $row->total]),
        ];
    }

    /** @return array<int, int|string> */
    public function anosDisponiveis(): array
    {
        return $this->repository->availableYears();
    }

    public function areaOpcaoDetalhe(string $ano, ?int $limit): mixed
    {
        return $this->repository->optionAreaRows($ano, $limit)->map(fn ($row): array => [
            'id' => $row->id, 'nome' => $row->nome, 'cidade' => $row->cidade?->city, 'estado' => $row->cidade?->state_code,
            'responsavel' => $row->responsavel?->name, 'total_unidades' => (int) $row->total_unidades,
            'vgv_total' => (float) $row->vgv_total, 'vgv_formatado' => 'R$ '.number_format((float) $row->vgv_total, 2, ',', '.'),
        ]);
    }

    /**
     * @param  array<int, string>  $include
     * @return array<string, mixed>
     */
    public function buildOverview(array $include, ?string $ano, ?string $mes, int $meses, int $topLimit, int $areaLimit, ?string $responsavelId): array
    {
        $payload = [];
        if ($this->shouldInclude($include, 'cards')) {
            $payload['cards'] = $this->cards();
        }
        if ($this->shouldInclude($include, 'status_chart') || $this->shouldInclude($include, 'anos_disponiveis')) {
            $status = $this->statusChart($ano);
            if ($this->shouldInclude($include, 'status_chart')) {
                $payload['status_chart'] = $status['status_data'];
            }
            if ($this->shouldInclude($include, 'anos_disponiveis')) {
                $payload['anos_disponiveis'] = $status['anos_disponiveis'];
            }
        }
        if ($this->shouldInclude($include, 'cadastros_mensais')) {
            $payload['cadastros_mensais'] = $this->cadastrosMensais($ano, $meses, null, null)['cadastros'];
        }
        if ($this->shouldInclude($include, 'top_cidades')) {
            $payload['top_cidades'] = $this->topCidades($ano && $mes ? 'mes' : ($ano ? 'ano' : 'geral'), $ano, $mes, $topLimit);
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
            $payload['cadastros_mensais_responsavel'] = $this->cadastrosMensaisPorResponsavel($ano, $meses, null, null, $responsavelId);
        }
        if ($this->shouldInclude($include, 'area_opcao_detalhe')) {
            $payload['area_opcao_detalhe'] = $ano ? $this->areaOpcaoDetalhe($ano, $areaLimit) : [];
        }

        return $payload;
    }

    /**
     * @param  array<int, string>  $active
     * @param  array<int, string>  $signed
     * @param  array<int, string>  $negotiation
     * @return array<string, float|int>
     */
    private function portfolioTotals(array $active, array $signed, array $negotiation): array
    {
        $pipeline = $this->repository->productTotalsForStatuses($active);
        $signedTotals = $this->repository->productTotalsForStatuses($signed);
        $negotiationTotals = $this->repository->productTotalsForStatuses($negotiation);

        return ['vgv_pipeline' => $pipeline['vgv'], 'vgv_signed' => $signedTotals['vgv'], 'vgv_negotiation' => $negotiationTotals['vgv'], 'vgv_weighted' => $this->weightedVgv(), 'units_pipeline' => $pipeline['units'], 'units_signed' => $signedTotals['units'], 'units_negotiation' => $negotiationTotals['units']];
    }

    private function weightedVgv(): float
    {
        $weights = [WorkflowStatus::EM_ANALISE->value => .1, WorkflowStatus::AGUARDANDO_VIABILIDADE->value => .2, WorkflowStatus::VIABILIDADE_APROVADA->value => .45, WorkflowStatus::AGUARDANDO_COMITE->value => .55, WorkflowStatus::NEGOCIACAO_MINUTA->value => .7, WorkflowStatus::CONTRATO_ASSINADO->value => 1, WorkflowStatus::LEGALIZANDO->value => 1, WorkflowStatus::LEGALIZADO_FINALIZADO->value => 1];

        return (float) $this->repository->vgvByStatus()->sum(fn ($row): float => (float) $row->vgv * ($weights[$row->workflow_status_code] ?? 0));
    }

    /** @return array<int, array<string, mixed>> */
    private function pipelineFunnel(int $staleDays): array
    {
        $statuses = collect(WorkflowStatus::cases());
        $counts = $this->repository->statusCounts(null, null)->pluck('total', 'workflow_status_code');

        return $statuses->map(function (WorkflowStatus $status, int $index) use ($counts, $staleDays): array {
            $count = (int) ($counts[$status->value] ?? 0);
            $next = WorkflowStatus::cases()[$index + 1] ?? null;
            $products = $this->repository->productTotalsForStatuses([$status->value]);

            return ['status_code' => $status->value, 'status_nome' => $status->label(), 'status_cor' => $status->color(), 'stage' => $status->stage(), 'total' => $count, 'vgv_total' => $products['vgv'], 'total_unidades' => $products['units'], 'stale_count' => $this->repository->staleTerrenoCount($status->value, $staleDays), 'avg_days_in_status' => $this->averageDaysInStatus($status->value), 'conversion_to_next' => $next ? $this->percent((int) ($counts[$next->value] ?? 0), $count) : null];
        })->all();
    }

    private function averageDaysInStatus(string $status): ?float
    {
        $items = $this->repository->terrenosInStatus($status);
        if ($items->isEmpty()) {
            return null;
        }

        return round((float) $items->avg(function (Terreno $terreno): int {
            $date = $terreno->workflow_status_changed_at ?? $terreno->created_at;

            return $date ? (int) $date->diffInDays(now()) : 0;
        }), 1);
    }

    /** @return array<string, mixed> */
    private function financialHealth(): array
    {
        $items = $this->repository->currentViabilities();
        $withDre = $items->filter(fn (Viabilidade $item): bool => is_array($item->resultados_dre));
        $vgv = $profit = 0.0;
        $margins = $rois = [];
        foreach ($withDre as $item) {
            $indicators = $item->resultados_dre['indicadores'] ?? [];
            $vgv += (float) ($indicators['vgv_total'] ?? 0);
            $profit += (float) ($indicators['lucro_liquido'] ?? ($item->resultados_dre['lucro_liquido_projeto'] ?? 0));
            if (isset($indicators['margem_liquida_percentual'])) {
                $margins[] = (float) $indicators['margem_liquida_percentual'];
            }
            if (isset($indicators['roi_percentual'])) {
                $rois[] = (float) $indicators['roi_percentual'];
            }
        }

        return ['current_viabilities' => $items->count(), 'with_dre' => $withDre->count(), 'missing_dre' => $items->count() - $withDre->count(), 'approval_pending' => $items->where('approval_status', 'pendente')->count(), 'vgv_pipeline' => round($vgv, 2), 'profit_pipeline' => round($profit, 2), 'avg_margin_percent' => $margins ? round(array_sum($margins) / count($margins), 2) : 0.0, 'avg_roi_percent' => $rois ? round(array_sum($rois) / count($rois), 2) : 0.0, 'by_status' => $items->groupBy(fn (Viabilidade $item): string => $item->status ?? 'sem_status')->map(fn ($group, string $status): array => ['status' => $status, 'total' => $group->count()])->values()->all()];
    }

    /** @return array<string, array<string, mixed>> */
    private function dashboardAlerts(int $staleDays, int $criticalDays, int $limit): array
    {
        return [
            'stale_terrenos' => ['key' => 'stale_terrenos', 'label' => "Terrenos sem atualização há {$staleDays}+ dias", 'count' => $this->repository->staleTerrenosTotal($staleDays), 'severity' => 'warning', 'items' => $this->mapTerrenos($this->repository->staleTerrenos($staleDays, $limit))],
            'missing_data' => ['key' => 'missing_data', 'label' => 'Terrenos com cadastro incompleto', 'count' => $this->repository->incompleteTerrenosTotal(), 'severity' => 'info', 'items' => $this->mapTerrenos($this->repository->incompleteTerrenos($limit))],
            'viability_pending' => ['key' => 'viability_pending', 'label' => 'Viabilidades aguardando aprovação', 'count' => $this->repository->pendingViabilityCount(), 'severity' => 'warning', 'items' => []],
            'committee_open_issues' => ['key' => 'committee_open_issues', 'label' => 'Pendências abertas de comitê', 'count' => $this->repository->openCommitteeIssueCount(), 'severity' => 'danger', 'items' => $this->repository->openCommitteeIssues($limit)->map(fn (ComitePendencia $item): array => ['id' => $item->id, 'terreno_id' => $item->terreno_id, 'title' => $item->title, 'severity' => $item->severity, 'due_date' => $item->due_date?->format('Y-m-d')])->all()],
            'legalization_critical' => ['key' => 'legalization_critical', 'label' => 'Pendências críticas de legalização', 'count' => $this->repository->criticalLegalizationIssueCount($criticalDays), 'severity' => 'danger', 'items' => $this->repository->criticalLegalizationIssues($criticalDays, $limit)->map(fn (LegalizacaoPendencia $item): array => ['id' => $item->id, 'legalizacao_id' => $item->legalizacao_id, 'title' => $item->title, 'severity' => $item->severity, 'is_critical' => (bool) $item->is_critical, 'due_date' => $item->due_date?->format('Y-m-d')])->all()],
            'overdue_tasks' => ['key' => 'overdue_tasks', 'label' => 'Tarefas vencidas', 'count' => $this->repository->overdueTaskCount(), 'severity' => 'warning', 'items' => $this->mapTasks($this->repository->overdueTasks($limit))],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function teamPerformance(int $limit): array
    {
        return $this->repository->teamRows($limit)->map(fn ($row): array => ['responsavel_id' => $row->responsavel_id, 'responsavel_nome' => $row->responsavel?->name ?? 'Não informado', 'responsavel_email' => $row->responsavel?->email, 'total' => (int) $row->total, 'contratos' => (int) $row->contratos, 'encerrados' => (int) $row->encerrados, 'conversion_rate' => $this->percent((int) $row->contratos, (int) $row->total)])->all();
    }

    /** @return array<string, int|float> */
    private function operationalHealth(int $staleDays): array
    {
        $totals = $this->repository->operationalTotals($staleDays);
        $totals['legalization_avg_progress'] = round((float) $totals['legalization_avg_progress'], 1);

        return $totals;
    }

    /** @return array<int, array<string, mixed>> */
    private function geography(int $limit): array
    {
        $cities = $this->repository->cityCounts('geral', null, null, $limit);
        $products = $this->repository->productTotalsByCity($cities->pluck('cidade_code')->filter()->values()->all());

        return $cities->map(function ($row, int $index) use ($products): array {
            $totals = $products->get($row->cidade_code);

            return ['posicao' => $index + 1, 'cidade_code' => $row->cidade_code, 'cidade' => $row->cidade?->city, 'estado' => $row->cidade?->state_code, 'total' => (int) $row->total, 'vgv_total' => (float) ($totals?->vgv ?? 0), 'total_unidades' => (int) ($totals?->units ?? 0)];
        })->all();
    }

    /** @return array<string, mixed> */
    private function monthlyPayload(mixed $row): array
    {
        return ['ano' => $row->ano, 'mes' => $row->mes, 'mes_nome' => Carbon::createFromDate($row->ano, $row->mes)->translatedFormat('F'), 'periodo' => Carbon::createFromDate($row->ano, $row->mes)->format('Y-m'), 'total' => $row->total];
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function mapTerrenos(iterable $items): array
    {
        $payload = [];
        foreach ($items as $item) {
            $payload[] = ['id' => $item->id, 'nome' => $item->nome, 'status_code' => $item->workflow_status_code, 'status_nome' => $this->workflowStatusLabel($item->workflow_status_code), 'responsavel_nome' => $item->responsavel?->name, 'cidade' => $item->cidade?->city, 'estado' => $item->cidade?->state_code, 'updated_at' => $item->updated_at?->toIso8601String()];
        }

        return $payload;
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function mapTasks(iterable $items): array
    {
        $payload = [];
        foreach ($items as $task) {
            $payload[] = ['id' => $task->id, 'terreno_id' => $task->terreno_id, 'title' => $task->title, 'priority' => $task->priority, 'due_date' => $task->due_date?->format('Y-m-d'), 'assigned_to_name' => $task->assignedUser?->name];
        }

        return $payload;
    }

    private function percent(int|float $part, int|float $total): float
    {
        return (float) $total === 0.0 ? 0.0 : round((float) $part / (float) $total * 100, 1);
    }

    private function safeDivide(int|float $value, int|float $divisor): float
    {
        return (float) $divisor === 0.0 ? 0.0 : round((float) $value / (float) $divisor, 2);
    }

    /** @param array<int, string> $include */
    private function shouldInclude(array $include, string $key): bool
    {
        return in_array('*', $include, true) || in_array($key, $include, true);
    }
}
