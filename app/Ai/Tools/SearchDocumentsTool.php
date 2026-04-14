<?php

namespace App\Ai\Tools;

use App\Models\Tenant\Terreno;
use App\Services\AiEmbeddingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchDocumentsTool implements Tool
{
    public function __construct(
        protected AiEmbeddingService $embeddingService
    ) {}

    public function description(): Stringable|string
    {
        return 'Busca semântica em documentos armazenados. Encontra trechos relevantes mesmo sem correspondência exata de palavras.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (Gate::denies('viewAny', Terreno::class)) {
            return 'Acesso negado: você não tem permissão para buscar documentos.';
        }

        $query = trim((string) ($request['query'] ?? ''));
        if ($query === '') {
            return 'Informe um parâmetro de busca (query).';
        }

        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        $limit = max(1, min((int) ($request['limit'] ?? 5), 20));

        try {
            $results = $this->embeddingService->searchSimilar(
                $query,
                $terrenoId > 0 ? $terrenoId : null,
                $limit
            );

            if ($results->isEmpty()) {
                return 'Nenhum documento encontrado para a busca.';
            }

            $payload = [
                'query' => $query,
                'total' => $results->count(),
                'resultados' => $results->values()->all(),
            ];

            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                ?: 'Falha ao serializar resultados.';
        } catch (\Exception $e) {
            return 'Erro na busca documental: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('Texto da busca semântica'),
            'terreno_id' => $schema->integer(),
            'limit' => $schema->integer(),
        ];
    }
}
