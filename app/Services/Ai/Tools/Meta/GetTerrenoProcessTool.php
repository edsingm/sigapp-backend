<?php

namespace App\Services\Ai\Tools\Meta;

use App\Services\Ai\Tools\GetComiteTool;
use App\Services\Ai\Tools\GetLegalizacaoTool;
use App\Services\Ai\Tools\GetNegociacaoTool;
use App\Services\Ai\Tools\GetViabilidadesTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Meta-tool: processos do terreno (viabilidade, legalização, comitê, negociação).
 */
class GetTerrenoProcessTool implements Tool
{
    use MetaToolSupport;

    public function description(): Stringable|string
    {
        return 'Processo do terreno. action=viabilidades|legalizacao|comite|negociacao. '
            .'Para "viabilidade aprovada" use action=viabilidades com approval_status=aprovada (não use workflow_status do terreno). '
            .'Filtre por terreno_id quando possível. legalizacao: include_etapas; viabilidades: somente_atual/include_dre.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $this->action($request, 'viabilidades');
        $forward = $this->forwardRequest($request);

        return match ($action) {
            'viabilidades' => $this->call(app(GetViabilidadesTool::class), $forward),
            'legalizacao' => $this->call(app(GetLegalizacaoTool::class), $forward),
            'comite' => $this->call(app(GetComiteTool::class), $forward),
            'negociacao' => $this->call(app(GetNegociacaoTool::class), $forward),
            default => $this->unknownAction($action, [
                'viabilidades',
                'legalizacao',
                'comite',
                'negociacao',
            ]),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->required()
                ->description('viabilidades | legalizacao | comite | negociacao')
                ->enum(['viabilidades', 'legalizacao', 'comite', 'negociacao']),
            'terreno_id' => $schema->integer()->description('ID do terreno (recomendado).'),
            'status' => $schema->string()->description('Filtro de status quando aplicável.'),
            'approval_status' => $schema->string()
                ->description('viabilidades: status de aprovação da viabilidade (ex.: aprovada, rejeitada, pendente) — distinto do status de workflow do terreno.'),
            'somente_atual' => $schema->boolean()->description('viabilidades'),
            'somente_decididas' => $schema->boolean()->description('viabilidades'),
            'include_dre' => $schema->string()
                ->description('viabilidades: summary|full')
                ->enum(['summary', 'full']),
            'include_etapas' => $schema->boolean()->description('legalizacao: detalhar etapas'),
            'limit' => $schema->integer()->description('Máximo de itens')->min(1),
        ];
    }
}
