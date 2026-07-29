<?php

namespace App\Services\Ai\Tools\Meta;

use App\Services\Ai\Tools\GetTasksTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Meta-tool: tarefas do sistema (wrapper estável do catálogo consolidado).
 */
class GetTasksHubTool implements Tool
{
    use MetaToolSupport;

    public function description(): Stringable|string
    {
        return 'Lista tarefas do sistema. Filtros: terreno_id, assigned_to, status, only_overdue, limit.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->call(new GetTasksTool, $this->forwardRequest($request, []));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'terreno_id' => $schema->integer()->description('Filtra tarefas de um terreno.'),
            'assigned_to' => $schema->integer()->description('ID do responsável.'),
            'status' => $schema->string()->description('Status da tarefa.'),
            'only_overdue' => $schema->boolean()->description('Se true, apenas atrasadas.'),
            'limit' => $schema->integer()->description('Máximo de itens.')->min(1),
        ];
    }
}
