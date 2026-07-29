<?php

namespace App\Services\Ai\Tools;

use App\Models\Central\Cidade;
use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\Viabilidade;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetDashboardSummaryTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Resumo executivo numérico do portfólio (totais por etapa, viabilidades, comitês, negociações, VGV estimado, top cidades). Use para "como está a carteira?" ou totais — NÃO para alertas de itens parados (use ProactiveMonitorTool) nem anomalias de dados (DetectAnomaliesTool).';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($deny = app(AiToolAuth::class)->ensureViewAny(
            Terreno::class,
            'Acesso negado: você não tem permissão para acessar dados do dashboard.'
        )) {
            return $deny;
        }

        $totalTerrenos = Terreno::query()->count();
        $porStage = Terreno::query()
            ->selectRaw('COUNT(*) as total, workflow_stage')
            ->groupBy('workflow_stage')
            ->get()
            ->mapWithKeys(fn ($r): array => [$r->workflow_stage ?? 'sem_etapa' => (int) $r->total])
            ->toArray();

        $viabilidadeAtivas = Viabilidade::query()
            ->where('is_current', true)
            ->selectRaw('COUNT(*) as total, status')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($r): array => [$r->status ?? 'sem_status' => (int) $r->total])
            ->toArray();

        $aprovaPendentes = Viabilidade::query()
            ->where('approval_status', 'pendente')
            ->where('approval_requested_at', '!=', null)
            ->count();

        $comitePendentes = ComiteRevisao::query()
            ->where('status', 'em_andamento')
            ->count();

        $negociacaoAtivas = Negociacao::query()
            ->whereNull('closed_at')
            ->count();

        $parados = Terreno::query()
            ->where('updated_at', '<', now()->subDays(30))
            ->count();

        $vgv = $this->sumCurrentVgv();

        $topCidadesRaw = Terreno::query()
            ->selectRaw('COUNT(*) as total, cidade_code, estado')
            ->groupBy('cidade_code', 'estado')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $cidadeNomes = Cidade::query()
            ->whereIn('code', $topCidadesRaw->pluck('cidade_code')->filter()->all())
            ->pluck('city', 'code');

        $topCidades = $topCidadesRaw->map(fn ($r): array => [
            'cidade' => $cidadeNomes[$r->cidade_code] ?? $r->cidade_code,
            'estado' => $r->estado,
            'total' => (int) $r->total,
        ])->values()->all();

        $payload = [
            'terrenos' => [
                'total' => $totalTerrenos,
                'por_stage' => $porStage,
                'parados_30_dias' => $parados,
            ],
            'viabilidades' => [
                'por_status' => $viabilidadeAtivas,
                'aprovacoes_pendentes' => $aprovaPendentes,
            ],
            'comite' => [
                'decisoes_pendentes' => $comitePendentes,
            ],
            'negociacoes_ativas' => $negociacaoAtivas,
            'vgv_estimado' => $vgv,
            'top_cidades' => $topCidades,
            'gerado_em' => now()->toIso8601String(),
        ];

        return AiToolResponse::ok($payload);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Soma VGV das viabilidades vigentes sem carregar todos os models em memória (PG).
     * Fallback seguro para SQLite/outros drivers.
     */
    private function sumCurrentVgv(): float
    {
        $connection = Viabilidade::query()->getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $total = Viabilidade::query()
                ->where('is_current', true)
                ->whereNotNull('resultados_dre')
                ->selectRaw("COALESCE(SUM(NULLIF(resultados_dre->>'vgv', '')::numeric), 0) as total")
                ->value('total');

            return (float) $total;
        }

        // SQLite/MySQL: pluck só a coluna JSON (cast array) em vez de carregar o model inteiro.
        return (float) Viabilidade::query()
            ->where('is_current', true)
            ->whereNotNull('resultados_dre')
            ->pluck('resultados_dre')
            ->sum(function (mixed $dre): float {
                if (is_string($dre)) {
                    $decoded = json_decode($dre, true);
                    $dre = is_array($decoded) ? $decoded : [];
                }

                return (float) (is_array($dre) ? ($dre['vgv'] ?? 0) : 0);
            });
    }
}
