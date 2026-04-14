<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetLegalizacaoTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Consulta o status de legalização de um terreno, incluindo etapas e pendências.';
    }

    public function handle(Request $request): Stringable|string
    {
        $terrenoId = (int) ($request['terreno_id'] ?? 0);

        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar legalizações.';
        }

        $query = Legalizacao::query()
            ->with(['etapas' => function ($q) {
                $q->select(['id', 'legalizacao_id', 'nome', 'status', 'percentual', 'prazo_fim', 'custo_previsto', 'custo_realizado', 'ordem']);
            }, 'pendencias'])
            ->orderByDesc('created_at');

        if ($terrenoId > 0) {
            $query->where('terreno_id', $terrenoId);
        }

        $limit = max(1, min((int) ($request['limit'] ?? 10), 50));
        $legalizacoes = $query->limit($limit)->get();

        if ($legalizacoes->isEmpty()) {
            return 'Nenhuma legalização encontrada'.($terrenoId > 0 ? " para o terreno {$terrenoId}." : '.');
        }

        $payload = [
            'total' => $legalizacoes->count(),
            'items' => $legalizacoes->map(static function (Legalizacao $item): array {
                $etapas = $item->etapas->map(fn ($e): array => [
                    'nome' => $e->nome,
                    'status' => $e->status,
                    'percentual' => $e->percentual,
                    'prazo_fim' => optional($e->prazo_fim)?->toDateString(),
                    'custo_previsto' => $e->custo_previsto,
                    'custo_realizado' => $e->custo_realizado,
                ]);

                $pendencias = $item->pendencias->map(fn ($p): array => [
                    'tipo' => $p->tipo,
                    'descricao' => $p->descricao,
                    'status' => $p->status,
                ]);

                $atrasadas = $etapas->filter(fn ($e) => $e['status'] === 'atrasada' ||
                    ($e['status'] !== 'concluida' && $e['prazo_fim'] && strtotime($e['prazo_fim']) < time()));

                return [
                    'id' => $item->id,
                    'terreno_id' => $item->terreno_id,
                    'status' => $item->status,
                    'percentual_concluido' => $item->percentual_concluido,
                    'custo_total_previsto' => $item->custo_total_previsto,
                    'data_inicio' => optional($item->data_inicio_real)?->toDateString(),
                    'data_previsao_fim' => optional($item->data_conclusao_prevista)?->toDateString(),
                    'total_etapas' => $etapas->count(),
                    'etapas' => $etapas->values(),
                    'total_pendencias' => $pendencias->count(),
                    'pendencias' => $pendencias->values(),
                    'etapas_atrasadas' => $atrasadas->count(),
                    'observacoes' => $item->observacoes,
                    'updated_at' => optional($item->updated_at)?->toAtomString(),
                ];
            })->all(),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar legalizações.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer(),
            'limit' => $schema->integer(),
        ];
    }
}
