<?php

namespace App\Services\Ai\Tools;

use App\Enums\LegalizacaoEtapaStatus;
use App\Models\Tenant\Legalizacao;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetLegalizacaoTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Consulta legalização de terreno. Por padrão retorna resumo (sem lista completa de etapas). Use include_etapas=true para detalhar etapas e pendências.';
    }

    public function handle(Request $request): Stringable|string
    {
        $auth = app(AiToolAuth::class);
        if ($deny = $auth->ensureFeature(
            'legalizations',
            'Acesso negado: seu plano não inclui legalizações.'
        )) {
            return $deny;
        }

        if ($deny = $auth->ensureViewAny(
            Legalizacao::class,
            'Acesso negado: você não tem permissão para acessar legalizações.'
        )) {
            return $deny;
        }

        $terrenoId = (int) ($request['terreno_id'] ?? 0);

        if ($terrenoId > 0) {
            $terrenoOrDeny = $auth->ensureTerrenoView($terrenoId);
            if (is_string($terrenoOrDeny)) {
                return $terrenoOrDeny;
            }
        }

        $includeEtapas = filter_var($request['include_etapas'] ?? false, FILTER_VALIDATE_BOOL);

        $with = ['pendencias'];
        if ($includeEtapas) {
            $with['etapas'] = function ($q) {
                $q->select(['id', 'legalizacao_id', 'titulo', 'status', 'percentual', 'fim_planejado', 'valor_custo', 'custo_pago', 'custos', 'ordem']);
            };
        } else {
            $with['etapas'] = function ($q) {
                $q->select(['id', 'legalizacao_id', 'titulo', 'status', 'percentual', 'fim_planejado', 'ordem']);
            };
        }

        $query = Legalizacao::query()
            ->with($with)
            ->orderByDesc('created_at');

        if ($terrenoId > 0) {
            $query->where('terreno_id', $terrenoId);
        }

        $limit = AiToolResponse::clampLimit($request['limit'] ?? 10);
        $total = (int) $query->count();
        $legalizacoes = $query->limit($limit)->get();

        if ($legalizacoes->isEmpty()) {
            return AiToolResponse::empty(
                'Nenhuma legalização encontrada'.($terrenoId > 0 ? " para o terreno {$terrenoId}." : '.'),
                ['items' => [], 'meta' => AiToolResponse::listMeta($total, 0, $limit)]
            );
        }

        $items = $legalizacoes->map(static function (Legalizacao $item) use ($includeEtapas): array {
            $etapas = $item->etapas->map(fn ($e): array => [
                'nome' => $e->titulo,
                'status' => $e->status instanceof LegalizacaoEtapaStatus ? $e->status->value : $e->status,
                'percentual' => $e->percentual,
                'prazo_fim' => optional($e->fim_planejado)?->toDateString(),
                'custo_previsto' => $includeEtapas ? (float) $e->valor_custo : null,
                'custo_realizado' => $includeEtapas
                    ? ($e->custo_pago ? (float) $e->valor_custo : 0)
                    : null,
            ]);

            $atrasadas = $etapas->filter(fn ($e) => $e['status'] === LegalizacaoEtapaStatus::ATRASADA->value ||
                ($e['status'] !== LegalizacaoEtapaStatus::CONCLUIDA->value && $e['prazo_fim'] && strtotime((string) $e['prazo_fim']) < time()));

            $base = [
                'id' => $item->id,
                'terreno_id' => $item->terreno_id,
                'status' => $item->status,
                'percentual_concluido' => $item->percentual_concluido,
                'custo_total_previsto' => $item->custo_total_previsto,
                'data_inicio' => optional($item->data_inicio_real)?->toDateString(),
                'data_previsao_fim' => optional($item->data_conclusao_prevista)?->toDateString(),
                'total_etapas' => $etapas->count(),
                'etapas_atrasadas' => $atrasadas->count(),
                'total_pendencias' => $item->pendencias->count(),
                'updated_at' => optional($item->updated_at)?->toAtomString(),
            ];

            if ($includeEtapas) {
                $base['etapas'] = $etapas->values();
                $base['pendencias'] = $item->pendencias->map(fn ($p): array => [
                    'tipo' => $p->prioridade,
                    'descricao' => $p->title,
                    'status' => $p->status,
                ])->values();
                $base['observacoes'] = $item->observacoes;
            } else {
                $base['pendencias_abertas'] = $item->pendencias
                    ->take(5)
                    ->map(fn ($p): string => (string) ($p->title ?? ''))
                    ->filter()
                    ->values()
                    ->all();
            }

            return $base;
        })->all();

        return AiToolResponse::ok([
            'include_etapas' => $includeEtapas,
            'items' => $items,
            'meta' => AiToolResponse::listMeta($total, count($items), $limit),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()
                ->description('ID do terreno para filtrar (opcional).'),
            'include_etapas' => $schema->boolean()
                ->description('Se true, inclui lista completa de etapas e pendências (padrão false = resumo).'),
            'limit' => $schema->integer()
                ->description('Máximo de itens (padrão 10, máximo 50).')
                ->min(1),
        ];
    }
}
