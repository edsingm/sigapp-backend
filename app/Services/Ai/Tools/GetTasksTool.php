<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetTasksTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Lista tarefas do sistema, filtráveis por responsável, status ou vencimento.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($deny = app(AiToolAuth::class)->ensureViewAny(
            Terreno::class,
            'Acesso negado: você não tem permissão para acessar tarefas.'
        )) {
            return $deny;
        }

        $query = Task::query()
            ->with(['terreno:id,nome,endereco', 'assignedUser:id,name'])
            ->orderBy('due_date');

        $terrenoId = (int) ($request['terreno_id'] ?? 0);

        if ($terrenoId > 0) {
            $terrenoOrDeny = app(AiToolAuth::class)->ensureTerrenoView($terrenoId);
            if (is_string($terrenoOrDeny)) {
                return $terrenoOrDeny;
            }
        }
        if ($terrenoId > 0) {
            $query->where('terreno_id', $terrenoId);
        }

        $assignedTo = (int) ($request['assigned_to'] ?? 0);
        if ($assignedTo > 0) {
            $query->where('assigned_to', $assignedTo);
        }

        $status = trim((string) ($request['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $onlyOverdue = filter_var($request['only_overdue'] ?? false, FILTER_VALIDATE_BOOL);
        if ($onlyOverdue) {
            $query->where('due_date', '<', now())
                ->whereNotIn('status', ['concluded', 'cancelled']);
        }

        $limit = AiToolResponse::clampLimit($request['limit'] ?? 50, default: 50, max: 50);
        $total = (int) $query->count();
        $tasks = $query->limit($limit)->get();

        if ($tasks->isEmpty()) {
            return AiToolResponse::empty(
                'Nenhuma tarefa encontrada'.($onlyOverdue ? ' atrasada.' : ' para os filtros informados.'),
                ['items' => [], 'meta' => AiToolResponse::listMeta($total, 0, $limit)]
            );
        }

        $items = $tasks->map(static function (Task $t): array {
            $isOverdue = $t->due_date && $t->due_date < now()
                && ! in_array($t->status, ['concluded', 'cancelled'], true);

            return [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'status' => $t->status,
                'priority' => $t->priority ?? 'normal',
                'due_date' => optional($t->due_date)?->toAtomString(),
                'is_overdue' => $isOverdue,
                'terreno' => $t->terreno ? [
                    'id' => $t->terreno->id,
                    'nome' => $t->terreno->nome,
                ] : null,
                'assigned_to' => $t->assignedUser ? [
                    'id' => $t->assignedUser->id,
                    'name' => $t->assignedUser->name,
                ] : null,
                'created_at' => optional($t->created_at)?->toAtomString(),
            ];
        });

        return AiToolResponse::ok([
            'items' => $items->all(),
            'resumo' => [
                'total' => $items->count(),
                'overdue' => $items->where('is_overdue', true)->count(),
                'open' => $items->where('status', 'open')->count(),
                'in_progress' => $items->where('status', 'in_progress')->count(),
                'concluded' => $items->where('status', 'concluded')->count(),
            ],
            'meta' => AiToolResponse::listMeta($total, $items->count(), $limit),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()->description('Filtra tarefas de um terreno.'),
            'assigned_to' => $schema->integer()->description('ID do responsável.'),
            'status' => $schema->string()->description('Status da tarefa (ex.: open, in_progress, concluded).'),
            'only_overdue' => $schema->boolean()->description('Se true, apenas tarefas atrasadas.'),
            'limit' => $schema->integer()->description('Máximo de itens (padrão 50).')->min(1),
        ];
    }
}
