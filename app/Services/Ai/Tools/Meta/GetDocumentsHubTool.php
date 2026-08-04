<?php

namespace App\Services\Ai\Tools\Meta;

use App\Services\Ai\Tools\AiEmbeddingService;
use App\Services\Ai\Tools\DocumentosTool;
use App\Services\Ai\Tools\SearchDocumentsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Meta-tool: listagem/análise de documentos e busca semântica.
 */
class GetDocumentsHubTool implements Tool
{
    use MetaToolSupport;

    public function description(): Stringable|string
    {
        return 'Documentos. action=list (metadados/filtros; document_id devolve detalhe + analysis de PDF se existir ou enfileira sob demanda), '
            .'search (busca semântica; exige query). Para conteúdo de PDF use list com document_id e leia analysis.summary/key_fields; '
            .'se status queued, informe que a análise foi enfileirada e não invente o conteúdo.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $this->action($request, 'list');
        $forward = $this->forwardRequest($request);

        return match ($action) {
            'list' => $this->call(new DocumentosTool, $forward),
            'search' => $this->call(
                new SearchDocumentsTool(app(AiEmbeddingService::class)),
                $forward
            ),
            default => $this->unknownAction($action, ['list', 'search']),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->required()
                ->description('list | search')
                ->enum(['list', 'search']),
            'query' => $schema->string()->description('search: texto da busca semântica'),
            'document_id' => $schema->integer()->description('list: analisa documento específico'),
            'terreno_id' => $schema->integer()->description('filtra por terreno'),
            'tipo' => $schema->string()->description('list: tipo do documento'),
            'categoria' => $schema->string()->description('list: categoria'),
            'status' => $schema->string()->description('list: status'),
            'limit' => $schema->integer()->description('máximo de itens')->min(1),
        ];
    }
}
