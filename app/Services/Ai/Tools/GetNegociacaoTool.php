<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Negociacao;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetNegociacaoTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Consulta negociações de terreno (proposta, modelo, eventos resumidos). Não devolve payload_json cru de eventos.';
    }

    public function handle(Request $request): Stringable|string
    {
        $auth = app(AiToolAuth::class);
        if ($deny = $auth->ensureFeature(
            'negotiation',
            'Acesso negado: seu plano não inclui negociações.'
        )) {
            return $deny;
        }

        if ($deny = $auth->ensureViewAny(
            Negociacao::class,
            'Acesso negado: você não tem permissão para acessar negociações.'
        )) {
            return $deny;
        }

        $query = Negociacao::query()
            ->with(['terreno:id,nome,endereco,cidade_code,estado', 'eventos'])
            ->orderByDesc('started_at');

        $terrenoId = (int) ($request['terreno_id'] ?? 0);

        if ($terrenoId > 0) {
            $terrenoOrDeny = $auth->ensureTerrenoView($terrenoId);
            if (is_string($terrenoOrDeny)) {
                return $terrenoOrDeny;
            }
            $query->where('terreno_id', $terrenoId);
        }

        $status = trim((string) ($request['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $limit = AiToolResponse::clampLimit($request['limit'] ?? 10);
        $total = (int) $query->count();
        $negociacoes = $query->limit($limit)->get();

        if ($negociacoes->isEmpty()) {
            return AiToolResponse::empty(
                'Nenhuma negociação encontrada'.($terrenoId > 0 ? " para o terreno {$terrenoId}." : '.'),
                ['items' => [], 'meta' => AiToolResponse::listMeta($total, 0, $limit)]
            );
        }

        $items = $negociacoes->map(static function (Negociacao $item): array {
            return [
                'id' => $item->id,
                'terreno_id' => $item->terreno_id,
                'terreno' => $item->terreno ? [
                    'nome' => $item->terreno->nome,
                    'endereco' => $item->terreno->endereco,
                ] : null,
                'status' => $item->status,
                'proposal_value' => $item->proposal_value,
                'business_model' => $item->business_model,
                'started_at' => optional($item->started_at)?->toAtomString(),
                'closed_at' => optional($item->closed_at)?->toAtomString(),
                'notes' => $item->notes,
                'eventos_count' => $item->eventos->count(),
                'eventos' => $item->eventos->map(function ($e): array {
                    $payload = $e->payload_json;
                    $resumo = null;
                    if (is_array($payload) && $payload !== []) {
                        $keys = array_slice(array_keys($payload), 0, 5);
                        $resumo = implode(', ', $keys);
                    }

                    return [
                        'tipo' => $e->event_type,
                        'descricao' => $e->notes ?? '',
                        'resumo_campos' => $resumo,
                        'data' => optional($e->happened_at)?->toAtomString(),
                    ];
                })->values()->all(),
                'created_at' => optional($item->created_at)?->toAtomString(),
            ];
        })->all();

        return AiToolResponse::ok([
            'items' => $items,
            'meta' => AiToolResponse::listMeta($total, count($items), $limit),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()
                ->description('ID do terreno para filtrar (opcional).'),
            'status' => $schema->string()
                ->description('Status da negociação (opcional).'),
            'limit' => $schema->integer()
                ->description('Máximo de itens (padrão 10, máximo 50).')
                ->min(1),
        ];
    }
}
