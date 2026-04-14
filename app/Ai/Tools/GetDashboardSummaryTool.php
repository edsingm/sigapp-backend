<?php

namespace App\Ai\Tools;

use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetDashboardSummaryTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Retorna um resumo executivo do portfólio: totais de terrenos por etapa, viabilidades, comitês e negociações pendentes.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar dados do dashboard.';
        }

        $totalTerrenos = Terreno::query()->count();
        $porStage = Terreno::query()
            ->selectRaw('COUNT(*) as total, workflow_stage')
            ->groupBy('workflow_stage')
            ->get()
            ->mapWithKeys(fn ($r): array => [$r->workflow_stage ?? 'sem_etapa' => (int) $r->total])
            ->toArray();

        // Viabilidades ativas
        $viabilidadeAtivas = Viabilidade::query()
            ->where('is_current', true)
            ->selectRaw('COUNT(*) as total, status')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($r): array => [$r->status ?? 'sem_status' => (int) $r->total])
            ->toArray();

        // Aprovações pendentes
        $aprovaPendentes = Viabilidade::query()
            ->where('approval_status', 'pendente')
            ->where('approval_requested_at', '!=', null)
            ->count();

        // Comitês com decisões pendentes
        $comitePendentes = ComiteRevisao::query()
            ->where('status', 'em_andamento')
            ->count();

        // Negociações em andamento
        $negociacaoAtivas = Negociacao::query()
            ->whereNull('closed_at')
            ->count();

        // Terrenos parados (> 30 dias sem atualização)
        $parados = Terreno::query()
            ->where('updated_at', '<', now()->subDays(30))
            ->count();

        // VGV total das viabilidades ativas
        $vgv = Viabilidade::query()
            ->where('is_current', true)
            ->whereIn('status', ['ativo', 'aprovada'])
            ->get()
            ->sum(fn ($v): float => $v->resultados_dre['indicadores']['vgv_total'] ?? 0);

        // Top cidades
        $topCidades = Terreno::query()
            ->selectRaw('COUNT(*) as total, cidade_code, estado')
            ->groupBy('cidade_code', 'estado')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($r): array => [
                'cidade_code' => $r->cidade_code,
                'estado' => $r->estado,
                'total' => (int) $r->total,
            ])
            ->values()
            ->all();

        $payload = [
            'terrenos' => [
                'total' => $totalTerrenos,
                'por_stage' => $porStage,
                'parados_30_dias' => $parados,
            ],
            'viabilidades' => [
                'por_status' => $viabilidadeAtivas,
                'aprovacoes pendentes' => $aprovaPendentes,
            ],
            'comite' => [
                'decisoes_pendentes' => $comitePendentes,
            ],
            'negociacoes_ativas' => $negociacaoAtivas,
            'vgv_estimado' => $vgv,
            'top_cidades' => $topCidades,
            'gerado_em' => now()->toIso8601String(),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar resumo do dashboard.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
