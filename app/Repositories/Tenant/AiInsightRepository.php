<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class AiInsightRepository
{
    public function countActive(): int
    {
        return Terreno::whereNotIn('workflow_status_code', ['descartado', 'arquivado'])->count();
    }

    public function countByStatus(string $status): int
    {
        return Terreno::where('workflow_status_code', $status)->count();
    }

    public function countFullyActive(): int
    {
        return Terreno::whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])->count();
    }

    public function countCreatedSince(\DateTimeInterface $since): int
    {
        return Terreno::where('created_at', '>=', $since)->count();
    }

    public function countCreatedBetween(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return Terreno::whereBetween('created_at', [$start, $end])->count();
    }

    /**
     * Total de terrenos ativos agrupado por cidade.
     *
     * @return Collection<int, Terreno>
     */
    public function topCitiesByCount(int $limit): Collection
    {
        return Terreno::query()
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado'])
            ->whereNotNull('cidade_code')
            ->selectRaw('cidade_code, COUNT(*) as total')
            ->groupBy('cidade_code')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /**
     * Cidades com mais viabilidades registradas.
     *
     * @return Collection<int, Viabilidade>
     */
    public function topCitiesByViabilidade(int $limit): Collection
    {
        return Viabilidade::query()
            ->withTrashed()
            ->join('terrenos', 'terrenos.id', '=', 'viabilidades.terreno_id')
            ->whereNotNull('terrenos.cidade_code')
            ->whereNotNull('resultados_dre')
            ->selectRaw('terrenos.cidade_code as cidade, COUNT(*) as total_viabs')
            ->groupBy('terrenos.cidade_code')
            ->orderByDesc('total_viabs')
            ->limit($limit)
            ->get();
    }

    /**
     * Contagem de terrenos ativos por estágio de workflow.
     *
     * @return SupportCollection<string, int>
     */
    public function stageCounts(): SupportCollection
    {
        return Terreno::query()
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])
            ->selectRaw('workflow_stage, COUNT(*) as count')
            ->groupBy('workflow_stage')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->workflow_stage => $row->count]);
    }

    /**
     * Estatísticas de terrenos por cidade (totais, finalizados, valor médio).
     *
     * @return Collection<int, Terreno>
     */
    public function trendsByCity(int $limit): Collection
    {
        return Terreno::query()
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado'])
            ->whereNotNull('cidade_code')
            ->selectRaw("
                cidade_code,
                COUNT(*) as total_terrenos,
                COUNT(CASE WHEN workflow_status_code = 'legalizado_finalizado' THEN 1 END) as finalizados,
                AVG(COALESCE(valor, 0)) as avg_valor,
                MAX(created_at) as last_cadastro
            ")
            ->groupBy('cidade_code')
            ->orderByDesc('total_terrenos')
            ->limit($limit)
            ->get();
    }

    /**
     * Cadastros mensais dos últimos N meses.
     *
     * @return Collection<int, Terreno>
     */
    public function monthlyTrends(\DateTimeInterface $since): Collection
    {
        return Terreno::query()
            ->selectRaw("
                TO_CHAR(created_at, 'YYYY-MM') as month,
                COUNT(*) as cadastros,
                COUNT(CASE WHEN workflow_stage = 'captacao' THEN 1 END) as captacoes,
                COUNT(CASE WHEN workflow_status_code IN ('descartado', 'arquivado') THEN 1 END) as descarte
            ")
            ->where('created_at', '>=', $since)
            ->groupByRaw("TO_CHAR(created_at, 'YYYY-MM')")
            ->orderByRaw("TO_CHAR(created_at, 'YYYY-MM')")
            ->get();
    }

    /**
     * Estatísticas de terrenos agrupadas por cidade para comparação.
     *
     * @return Collection<int, Terreno>
     */
    public function comparisonByCity(): Collection
    {
        return Terreno::query()
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado'])
            ->whereNotNull('cidade_code')
            ->selectRaw("
                cidade_code,
                COUNT(*) as total,
                COUNT(CASE WHEN workflow_status_code = 'legalizado_finalizado' THEN 1 END) as finalizados,
                COUNT(CASE WHEN workflow_status_code = 'descartado' THEN 1 END) as descartados
            ")
            ->groupBy('cidade_code')
            ->get();
    }

    /**
     * Estatísticas de terrenos por responsável.
     *
     * @return SupportCollection<int, object{responsavel_id: int|null, name: string, total: int, aprovados: int, em_analise: int, descartados: int}>
     */
    public function responsavelStats(int $limit): SupportCollection
    {
        return DB::table('terrenos')
            ->leftJoin('users', 'users.id', '=', 'terrenos.responsavel_id')
            ->selectRaw("
                terrenos.responsavel_id,
                COALESCE(users.name, 'Sem responsável') as name,
                COUNT(*) as total,
                COUNT(CASE WHEN terrenos.workflow_status_code IN ('viabilidade_aprovada', 'aguardando_comite', 'negociacao_minuta', 'contrato_assinado', 'legalizando', 'legalizado_finalizado') THEN 1 END) as aprovados,
                COUNT(CASE WHEN terrenos.workflow_status_code = 'em_analise' THEN 1 END) as em_analise,
                COUNT(CASE WHEN terrenos.workflow_status_code IN ('descartado', 'arquivado') THEN 1 END) as descartados
            ")
            ->whereNotIn('terrenos.workflow_status_code', ['descartado', 'arquivado'])
            ->groupBy('terrenos.responsavel_id', 'users.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }
}
