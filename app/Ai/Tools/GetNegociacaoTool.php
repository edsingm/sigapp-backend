<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetNegociacaoTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Consulta o status de negociações de um terreno, incluindo proposta, modelo de negócio e eventos.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar negociações.';
        }

        $query = Negociacao::query()
            ->with(['terreno:id,nome,endereco,cidade_code,estado', 'eventos'])
            ->orderByDesc('started_at');

        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        if ($terrenoId > 0) {
            $query->where('terreno_id', $terrenoId);
        }

        $status = trim((string) ($request['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $limit = max(1, min((int) ($request['limit'] ?? 10), 50));
        $negociacoes = $query->limit($limit)->get();

        if ($negociacoes->isEmpty()) {
            return 'Nenhuma negociação encontrada'.($terrenoId > 0 ? " para o terreno {$terrenoId}." : '.');
        }

        $payload = [
            'total' => $negociacoes->count(),
            'items' => $negociacoes->map(static function (Negociacao $item): array {
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
                    'eventos' => $item->eventos->map(fn ($e): array => [
                        'tipo' => $e->tipo ?? $e->type ?? 'N/A',
                        'descricao' => $e->descricao ?? $e->description ?? '',
                        'data' => optional($e->created_at)?->toAtomString(),
                    ])->values()->all(),
                    'created_at' => optional($item->created_at)?->toAtomString(),
                ];
            })->all(),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar negociações.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer(),
            'status' => $schema->string(),
            'limit' => $schema->integer(),
        ];
    }
}
