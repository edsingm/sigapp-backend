<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

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
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    /** @return array{total_terrenos: int, total_contrato_assinado: int, total_legalizando: int, vgv_contrato_assinado: float} */
    public function cardTotals(): array
    {
        return [
            'total_terrenos' => Terreno::query()->count(),
            'total_contrato_assinado' => Terreno::query()->where('workflow_status_code', WorkflowStatus::CONTRATO_ASSINADO->value)->count(),
            'total_legalizando' => Terreno::query()->where('workflow_status_code', WorkflowStatus::LEGALIZANDO->value)->count(),
            'vgv_contrato_assinado' => (float) Terreno::query()
                ->where('workflow_status_code', WorkflowStatus::CONTRATO_ASSINADO->value)
                ->join('terreno_produtos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
                ->whereNull('terrenos.deleted_at')
                ->sum(DB::raw('COALESCE(terreno_produtos.valor, 0) * COALESCE(terreno_produtos.unidades, 0)')),
        ];
    }

    /** @return array<int, int|string> */
    public function availableYears(): array
    {
        return Terreno::query()->select(DB::raw('DISTINCT '.SqlDateParts::year('created_at').' as ano'))
            ->whereNotNull('created_at')->orderBy('ano', 'desc')->pluck('ano')->toArray();
    }

    /** @return SupportCollection<int, mixed> */
    public function statusCounts(?CarbonInterface $from, ?string $year): SupportCollection
    {
        $query = Terreno::query()->select('workflow_status_code', DB::raw('COUNT(*) as total'));
        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        } elseif ($year !== null) {
            $query->whereYear('created_at', $year);
        }

        return $query->groupBy('workflow_status_code')->get();
    }

    /** @return SupportCollection<int, mixed> */
    public function monthlyRegistrationCounts(?string $year, ?CarbonInterface $from, ?CarbonInterface $to): SupportCollection
    {
        $query = Terreno::query()->select(
            DB::raw(SqlDateParts::yearAs('created_at', 'ano')),
            DB::raw(SqlDateParts::monthAs('created_at', 'mes')),
            DB::raw('COUNT(*) as total'),
        );
        if ($year !== null) {
            $query->whereYear('created_at', $year);
        } elseif ($from !== null) {
            $to !== null
                ? $query->whereBetween('created_at', [$from, $to])
                : $query->where('created_at', '>=', $from);
        }

        return $query->groupBy(DB::raw(SqlDateParts::year('created_at')), DB::raw(SqlDateParts::month('created_at')))
            ->orderBy('ano')->orderBy('mes')->get();
    }

    /** @return SupportCollection<int, mixed> */
    public function responsibleCounts(string $filter, ?string $year, ?string $month, ?int $limit): SupportCollection
    {
        $query = Terreno::query()->select('responsavel_id', DB::raw('COUNT(*) as total'))
            ->with('responsavel:id,name,email')->whereNotNull('responsavel_id');
        $this->applyPeriod($query, $filter, $year, $month);
        $query->groupBy('responsavel_id')->orderByDesc('total');
        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /** @return SupportCollection<int, mixed> */
    public function cityCounts(string $filter, ?string $year, ?string $month, int $limit): SupportCollection
    {
        $query = Terreno::query()->select('cidade_code', DB::raw('COUNT(*) as total'))
            ->with('cidade:code,city,state_code')->whereNotNull('cidade_code')->where('cidade_code', '!=', '');
        $this->applyPeriod($query, $filter, $year, $month);

        return $query->groupBy('cidade_code')->orderByDesc('total')->limit($limit)->get();
    }

    /** @return SupportCollection<int, mixed> */
    public function annualVgvRows(): SupportCollection
    {
        return Terreno::query()->leftJoin('terreno_produtos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
            ->whereNotNull('terrenos.data_opcao')->whereNull('terrenos.deleted_at')
            ->select(
                DB::raw(SqlDateParts::yearAs('COALESCE(terrenos.data_opcao, terrenos.created_at)', 'ano')),
                DB::raw('SUM(COALESCE(terreno_produtos.valor, 0) * COALESCE(terreno_produtos.unidades, 0)) as vgv_total'),
                DB::raw('SUM(COALESCE(terreno_produtos.unidades, 0)) as total_unidades'),
                DB::raw('COUNT(DISTINCT terrenos.id) as total_terrenos'),
            )->groupBy(DB::raw(SqlDateParts::year('COALESCE(terrenos.data_opcao, terrenos.created_at)')))
            ->orderBy('ano', 'desc')->get();
    }

    /**
     * @param  array<int, string>  $statuses
     * @return SupportCollection<int, mixed>
     */
    public function annualClosedUnitRows(array $statuses): SupportCollection
    {
        return TerrenoProduto::query()->join('terrenos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
            ->whereIn('terrenos.workflow_status_code', $statuses)->whereNull('terrenos.deleted_at')
            ->whereNotNull('terrenos.data_contrato')
            ->select(DB::raw(SqlDateParts::yearAs('terrenos.data_contrato', 'ano')), DB::raw('SUM(COALESCE(terreno_produtos.unidades, 0)) as total_unidades'), DB::raw('COUNT(DISTINCT terrenos.id) as total_terrenos'))
            ->groupBy(DB::raw(SqlDateParts::year('terrenos.data_contrato')))->orderBy('ano', 'desc')->get();
    }

    /** @return SupportCollection<int, mixed> */
    public function monthlyResponsibleRows(?string $year, ?CarbonInterface $from, ?CarbonInterface $to, ?string $responsibleId): SupportCollection
    {
        $query = Terreno::query()->select('responsavel_id', DB::raw(SqlDateParts::yearAs('created_at', 'ano')), DB::raw(SqlDateParts::monthAs('created_at', 'mes')), DB::raw('COUNT(*) as total'))
            ->with('responsavel:id,name')->whereNotNull('responsavel_id');
        if ($responsibleId !== null) {
            $query->where('responsavel_id', $responsibleId);
        }
        if ($year !== null) {
            $query->whereYear('created_at', $year);
        } elseif ($from !== null) {
            $to !== null
                ? $query->whereBetween('created_at', [$from, $to])
                : $query->where('created_at', '>=', $from);
        }

        return $query->groupBy('responsavel_id', DB::raw(SqlDateParts::year('created_at')), DB::raw(SqlDateParts::month('created_at')))
            ->orderByDesc('ano')->orderByDesc('mes')->orderByDesc('total')->get();
    }

    /** @param array<int, string> $statuses */
    public function terrenoCountForStatuses(array $statuses): int
    {
        return Terreno::query()->whereIn('workflow_status_code', $statuses)->count();
    }

    public function totalTerrenos(): int
    {
        return Terreno::query()->count();
    }

    /** @param array<int, string> $statuses */
    public function unitsForStatuses(array $statuses): int
    {
        return (int) TerrenoProduto::query()->whereHas('terreno', fn ($query) => $query->whereIn('workflow_status_code', $statuses))->sum('unidades');
    }

    public function currentMonthTerrenoCount(): int
    {
        return Terreno::query()->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
    }

    /** @return SupportCollection<int, mixed> */
    public function optionAreaRows(string $year, ?int $limit): SupportCollection
    {
        $query = Terreno::query()->whereYear('data_opcao', $year)->with(['cidade', 'responsavel'])
            ->withSum('terrenoProdutos as total_unidades', 'unidades')
            ->addSelect(['vgv_total' => TerrenoProduto::query()->select(DB::raw('SUM(COALESCE(valor, 0) * COALESCE(unidades, 0))'))->whereColumn('terreno_id', 'terrenos.id')]);
        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->orderByDesc('vgv_total')->get();
    }

    /**
     * @param  array<int, string>  $statuses
     * @return array{vgv: float, units: int}
     */
    public function productTotalsForStatuses(array $statuses): array
    {
        if ($statuses === []) {
            return ['vgv' => 0.0, 'units' => 0];
        }
        $row = TerrenoProduto::query()->join('terrenos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
            ->whereIn('terrenos.workflow_status_code', $statuses)->whereNull('terrenos.deleted_at')
            ->selectRaw('COALESCE(SUM(COALESCE(terreno_produtos.valor, 0) * COALESCE(terreno_produtos.unidades, 0)), 0) as vgv')
            ->selectRaw('COALESCE(SUM(COALESCE(terreno_produtos.unidades, 0)), 0) as units')->first();

        return ['vgv' => (float) ($row?->vgv ?? 0), 'units' => (int) ($row?->units ?? 0)];
    }

    /** @return SupportCollection<int, mixed> */
    public function vgvByStatus(): SupportCollection
    {
        return TerrenoProduto::query()->join('terrenos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
            ->whereNull('terrenos.deleted_at')->select('terrenos.workflow_status_code')
            ->selectRaw('SUM(COALESCE(terreno_produtos.valor, 0) * COALESCE(terreno_produtos.unidades, 0)) as vgv')
            ->groupBy('terrenos.workflow_status_code')->get();
    }

    public function staleTerrenoCount(string $status, int $days): int
    {
        return Terreno::query()->where('workflow_status_code', $status)->where('updated_at', '<', now()->subDays($days))->count();
    }

    /** @param  array<int, string>  $statuses */
    public function staleTerrenoCountForStatuses(array $statuses, int $days): int
    {
        return Terreno::query()->whereIn('workflow_status_code', $statuses)->where('updated_at', '<', now()->subDays($days))->count();
    }

    /** @return Collection<int, Terreno> */
    public function terrenosInStatus(string $status): Collection
    {
        return Terreno::query()->where('workflow_status_code', $status)->get(['workflow_status_changed_at', 'created_at']);
    }

    /** @return Collection<int, Viabilidade> */
    public function currentViabilities(): Collection
    {
        return Viabilidade::query()->where('is_current', true)->get(['id', 'terreno_id', 'status', 'approval_status', 'approval_requested_at', 'resultados_dre']);
    }

    /** @return Collection<int, Terreno> */
    public function staleTerrenos(int $days, int $limit): Collection
    {
        return $this->terrenoAlertQuery()->where('updated_at', '<', now()->subDays($days))->latest('updated_at')->limit($limit)->get();
    }

    public function staleTerrenosTotal(int $days): int
    {
        return Terreno::query()->where('updated_at', '<', now()->subDays($days))->count();
    }

    /** @return Collection<int, Terreno> */
    public function incompleteTerrenos(int $limit): Collection
    {
        return $this->terrenoAlertQuery()->where(fn ($query) => $query->whereNull('responsavel_id')->orWhereNull('cidade_code')->orWhereNull('area_calculada'))->latest('updated_at')->limit($limit)->get();
    }

    public function incompleteTerrenosTotal(): int
    {
        return Terreno::query()->where(fn ($query) => $query->whereNull('responsavel_id')->orWhereNull('cidade_code')->orWhereNull('area_calculada'))->count();
    }

    /** @return SupportCollection<int, mixed> */
    public function openCommitteeIssues(int $limit): SupportCollection
    {
        return ComitePendencia::query()->where('status', 'open')->orderByRaw('due_date is null, due_date asc')->limit($limit)->get(['id', 'terreno_id', 'title', 'severity', 'due_date']);
    }

    /** @return Collection<int, LegalizacaoPendencia> */
    public function criticalLegalizationIssues(int $days, int $limit): Collection
    {
        return $this->criticalLegalizationQuery($days)->orderByRaw('due_date is null, due_date asc')->limit($limit)->get(['id', 'legalizacao_id', 'title', 'severity', 'is_critical', 'due_date']);
    }

    /** @return SupportCollection<int, mixed> */
    public function overdueTasks(int $limit): SupportCollection
    {
        return $this->overdueTaskQuery()->with('assignedUser:id,name')->orderBy('due_date')->limit($limit)->get(['id', 'terreno_id', 'title', 'priority', 'due_date', 'assigned_to']);
    }

    /** @return SupportCollection<int, mixed> */
    public function teamRows(int $limit): SupportCollection
    {
        return Terreno::query()->select('responsavel_id', DB::raw('COUNT(*) as total'))
            ->selectRaw('SUM(CASE WHEN workflow_status_code = ? THEN 1 ELSE 0 END) as contratos', [WorkflowStatus::CONTRATO_ASSINADO->value])
            ->selectRaw('SUM(CASE WHEN workflow_status_code IN (?, ?) THEN 1 ELSE 0 END) as encerrados', WorkflowStatus::closure())
            ->with('responsavel:id,name,email')->whereNotNull('responsavel_id')->groupBy('responsavel_id')->orderByDesc('total')->limit($limit)->get();
    }

    /**
     * @param  array<int, string>  $cityCodes
     * @return SupportCollection<string, mixed>
     */
    public function productTotalsByCity(array $cityCodes): SupportCollection
    {
        if ($cityCodes === []) {
            return collect();
        }

        return TerrenoProduto::query()->join('terrenos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
            ->whereIn('terrenos.cidade_code', $cityCodes)->whereNull('terrenos.deleted_at')
            ->select('terrenos.cidade_code')->selectRaw('COALESCE(SUM(COALESCE(terreno_produtos.valor, 0) * COALESCE(terreno_produtos.unidades, 0)), 0) as vgv')
            ->selectRaw('COALESCE(SUM(COALESCE(terreno_produtos.unidades, 0)), 0) as units')->groupBy('terrenos.cidade_code')->get()->keyBy('cidade_code');
    }

    /** @return array<string, int|float> */
    public function operationalTotals(int $staleDays): array
    {
        return [
            'active_negotiations' => Negociacao::query()->whereNull('closed_at')->count(),
            'stale_negotiations' => Negociacao::query()->whereNull('closed_at')->where('updated_at', '<', now()->subDays($staleDays))->count(),
            'proposal_value_active' => (float) Negociacao::query()->whereNull('closed_at')->sum('proposal_value'),
            'signed_contracts' => Contrato::query()->whereNotNull('signed_at')->count(),
            'committee_open' => ComiteRevisao::query()->whereNull('decided_at')->count(),
            'committee_open_issues' => ComitePendencia::query()->where('status', 'open')->count(),
            'legalizations_active' => Legalizacao::query()->whereIn('status', ['planejado', 'em_andamento'])->count(),
            'legalization_avg_progress' => (float) (Legalizacao::query()->whereIn('status', ['planejado', 'em_andamento'])->avg('percentual_concluido') ?? 0),
            'legalization_delayed_stages' => LegalizacaoEtapa::query()->whereIn('status', ['atrasada', 'bloqueada'])->orWhere(fn ($query) => $query->where('status', '!=', 'concluida')->whereDate('fim_planejado', '<', today()))->count(),
            'legalization_critical_issues' => LegalizacaoPendencia::query()->whereNull('resolved_at')->where('is_critical', true)->count(),
            'open_projects' => Projeto::query()->whereNotIn('status', ['finalizado', 'cancelado'])->count(),
            'projects_ready_to_register' => Projeto::query()->whereNotNull('pronto_para_registro_em')->count(),
            'open_tasks' => Task::query()->whereNull('completed_at')->count(),
            'overdue_tasks' => Task::query()->whereNull('completed_at')->whereDate('due_date', '<', today())->count(),
        ];
    }

    public function openCommitteeIssueCount(): int
    {
        return ComitePendencia::query()->where('status', 'open')->count();
    }

    public function criticalLegalizationIssueCount(int $days): int
    {
        return $this->criticalLegalizationQuery($days)->count();
    }

    public function overdueTaskCount(): int
    {
        return $this->overdueTaskQuery()->count();
    }

    public function pendingViabilityCount(): int
    {
        return Viabilidade::query()->where('is_current', true)->where('approval_status', 'pendente')->whereNotNull('approval_requested_at')->count();
    }

    private function terrenoAlertQuery(): mixed
    {
        return Terreno::query()->with(['responsavel:id,name', 'cidade:code,city,state_code'])->select(['id', 'nome', 'responsavel_id', 'cidade_code', 'workflow_status_code', 'updated_at']);
    }

    private function criticalLegalizationQuery(int $days): mixed
    {
        return LegalizacaoPendencia::query()->whereNull('resolved_at')->where(fn ($query) => $query->where('is_critical', true)->orWhere('due_date', '<=', now()->addDays($days)->toDateString()));
    }

    private function overdueTaskQuery(): mixed
    {
        return Task::query()->whereNull('completed_at')->whereNotIn('status', ['done', 'concluida', 'concluído', 'completed'])->whereDate('due_date', '<', today());
    }

    private function applyPeriod(mixed $query, string $filter, ?string $year, ?string $month): void
    {
        if ($filter === 'ano' && $year) {
            $query->whereYear('created_at', $year);
        } elseif ($filter === 'mes' && $year && $month) {
            $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
        }
    }
}
