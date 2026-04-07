<?php

namespace App\Ai\Tools;

use App\Models\Tenant\ComiteRevisao;
use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetComiteTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Consulta o status de comitê de revisão de um terreno, incluindo pareceres e pendências por departamento.';
    }

    public function handle(Request $request): Stringable|string
    {
        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        $status = trim((string) ($request['status'] ?? ''));

        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar comitês.';
        }

        $query = ComiteRevisao::query()
            ->with([
                'pareceresDepartamento',
                'pendencias',
                'terreno:id,nome,endereco,cidade_code,estado',
            ])
            ->orderByDesc('created_at');

        if ($terrenoId > 0) {
            $query->where('terreno_id', $terrenoId);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $limit = max(1, min((int) ($request['limit'] ?? 10), 50));
        $comites = $query->limit($limit)->get();

        if ($comites->isEmpty()) {
            return 'Nenhum comitê de revisão encontrado'.($terrenoId > 0 ? " para o terreno {$terrenoId}." : '.');
        }

        $payload = [
            'total' => $comites->count(),
            'items' => $comites->map(static function (ComiteRevisao $item): array {
                $pareceres = $item->pareceresDepartamento->map(fn ($p): array => [
                    'departamento' => $p->department_name ?? $p->departamento_nome ?? 'N/A',
                    'posicao' => $p->posicao ?? 'pendente',
                    'comentarios' => $p->comentarios ?? '',
                ]);

                $pendencias = $item->pendencias->map(fn ($p): array => [
                    'descricao' => $p->descricao,
                    'status' => $p->status ?? 'aberta',
                    'responsavel' => $p->responsavel_nome ?? 'Não atribuído',
                ]);

                return [
                    'id' => $item->id,
                    'terreno_id' => $item->terreno_id,
                    'terreno' => $item->terreno ? [
                        'nome' => $item->terreno->nome,
                        'endereco' => $item->terreno->endereco,
                    ] : null,
                    'status' => $item->status,
                    'final_decision' => $item->final_decision,
                    'final_comments' => $item->final_comments,
                    'required_departments' => $item->required_departments,
                    'decided_by' => $item->decided_by,
                    'decided_at' => optional($item->decided_at)?->toAtomString(),
                    'total_pareceres' => $pareceres->count(),
                    'pareceres' => $pareceres->values(),
                    'total_pendencias' => $pendencias->count(),
                    'pendencias' => $pendencias->values(),
                    'created_at' => optional($item->created_at)?->toAtomString(),
                ];
            })->all(),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar comitês.';
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
