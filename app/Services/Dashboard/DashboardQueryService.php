<?php

namespace App\Services\Dashboard;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
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

        $totalOpcao = Terreno::whereIn('workflow_status_code', $this->negotiationStatuses())->count();

        $totalUnidadesOpcao = TerrenoProduto::join('terrenos', 'terreno_produtos.terreno_id', '=', 'terrenos.id')
            ->whereIn('terrenos.workflow_status_code', $this->negotiationStatuses())
            ->whereNull('terrenos.deleted_at')
            ->sum('terreno_produtos.unidades');

        $porCidade = Terreno::select('cidade_code', DB::raw('COUNT(*) as total'))
            ->with('cidade:code,city,state_code')
            ->whereNotNull('cidade_code')
            ->where('cidade_code', '!=', '')
            ->groupBy('cidade_code')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($item) => [
                'cidade' => $item->cidade?->city ?? 'Não Informada',
                'estado' => $item->cidade?->state_code ?? '-',
                'total' => $item->total,
            ]);

        return [
            'total_terrenos' => $totalTerrenos,
            'total_opcao' => $totalOpcao,
            'total_unidades_opcao' => (int) $totalUnidadesOpcao,
            'distribuicao_cidades' => $porCidade,
        ];
    }

    /**
     * @return array{status_data: mixed, anos_disponiveis: array}
     */
    public function statusChart(?string $ano): array
    {
        $anosDisponiveis = Terreno::select(DB::raw('DISTINCT '.SqlDateParts::year('created_at').' as ano'))
            ->whereNotNull('created_at')
            ->orderBy('ano', 'desc')
            ->pluck('ano')
            ->toArray();

        $query = Terreno::select('workflow_status_code', DB::raw('COUNT(*) as total'));

        if ($ano) {
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
}
