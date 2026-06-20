<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
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
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para acessar tarefas.';
        }

        $query = Task::query()
            ->with(['terreno:id,nome,endereco', 'assignedTo:id,name'])
            ->orderBy('due_date');

        $terrenoId = (int) ($request['terreno_id'] ?? 0);
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

        $limit = max(1, min((int) ($request['limit'] ?? 50), 200));
        $tasks = $query->limit($limit)->get();

        if ($tasks->isEmpty()) {
            return 'Nenhuma tarefa encontrada'.($onlyOverdue ? ' atrasada.' : ' para os filtros informados.');
        }

        $payload = [
            'total' => $tasks->count(),
            'items' => $tasks->map(static function (Task $t): array {
                $isOverdue = $t->due_date && $t->due_date < now()
                    && ! in_array($t->status, ['concluded', 'cancelled']);

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
                    'assigned_to' => $t->assignedTo ? [
                        'id' => $t->assignedTo->id,
                        'name' => $t->assignedTo->name,
                    ] : null,
                    'created_at' => optional($t->created_at)?->toAtomString(),
                ];
            })->all(),
            'resumo' => [
                'total' => $tasks->count(),
                'overdue' => $tasks->where('is_overdue', true)->count(),
                'open' => $tasks->where('status', 'open')->count(),
                'in_progress' => $tasks->where('status', 'in_progress')->count(),
                'concluded' => $tasks->where('status', 'concluded')->count(),
            ],
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: 'Falha ao serializar tarefas.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer(),
            'assigned_to' => $schema->integer(),
            'status' => $schema->string(),
            'only_overdue' => $schema->boolean(),
            'limit' => $schema->integer(),
        ];
    }
}
