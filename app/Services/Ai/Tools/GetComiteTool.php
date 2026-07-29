<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\ComiteRevisao;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
        $auth = app(AiToolAuth::class);
        if ($deny = $auth->ensureFeature(
            'committee',
            'Acesso negado: seu plano não inclui comitê de revisão.'
        )) {
            return $deny;
        }

        if ($deny = $auth->ensureViewAny(
            ComiteRevisao::class,
            'Acesso negado: você não tem permissão para acessar comitês.'
        )) {
            return $deny;
        }

        $terrenoId = (int) ($request['terreno_id'] ?? 0);

        if ($terrenoId > 0) {
            $terrenoOrDeny = app(AiToolAuth::class)->ensureTerrenoView($terrenoId);
            if (is_string($terrenoOrDeny)) {
                return $terrenoOrDeny;
            }
        }
        $status = trim((string) ($request['status'] ?? ''));

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

        $limit = AiToolResponse::clampLimit($request['limit'] ?? 10);
        $total = (int) $query->count();
        $comites = $query->limit($limit)->get();

        if ($comites->isEmpty()) {
            return AiToolResponse::empty(
                'Nenhum comitê de revisão encontrado'.($terrenoId > 0 ? " para o terreno {$terrenoId}." : '.'),
                ['items' => [], 'meta' => AiToolResponse::listMeta($total, 0, $limit)]
            );
        }

        $items = $comites->map(static function (ComiteRevisao $item): array {
            $pareceres = $item->pareceresDepartamento->map(fn ($p): array => [
                'departamento' => $p->department_code,
                'posicao' => $p->decision,
                'comentarios' => $p->comments ?? '',
            ]);

            $pendencias = $item->pendencias->map(fn ($p): array => [
                'descricao' => $p->description ?? $p->title,
                'status' => $p->status ?? 'aberta',
                'responsavel_id' => $p->responsible_user_id,
                'departamento' => $p->department_code,
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
                ->description('Status do comitê (opcional).'),
            'limit' => $schema->integer()
                ->description('Máximo de itens (padrão 10, máximo 50).')
                ->min(1),
        ];
    }
}
