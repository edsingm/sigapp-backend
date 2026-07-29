<?php

namespace App\Services\Ai\Tools;

use App\Models\Tenant\Documento;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
        $auth = app(AiToolAuth::class);
        if ($deny = $auth->ensureFeature(
            'documents.intelligence',
            'Acesso negado: seu plano não inclui busca inteligente em documentos.'
        )) {
            return $deny;
        }

        if ($deny = $auth->ensureViewAny(
            Documento::class,
            'Acesso negado: você não tem permissão para buscar documentos.'
        )) {
            return $deny;
        }

        $query = trim((string) ($request['query'] ?? ''));
        if ($query === '') {
            return AiToolResponse::validation('Informe um parâmetro de busca (query).');
        }

        $terrenoId = (int) ($request['terreno_id'] ?? 0);
        $limit = AiToolResponse::clampLimit($request['limit'] ?? 5, default: 5, max: 20);

        if ($terrenoId > 0) {
            $terrenoOrDeny = $auth->ensureTerrenoView($terrenoId);
            if (is_string($terrenoOrDeny)) {
                return $terrenoOrDeny;
            }
        }

        try {
            $results = $this->embeddingService->searchSimilar(
                $query,
                $terrenoId > 0 ? $terrenoId : null,
                $limit
            );

            if ($terrenoId <= 0 && $results->isNotEmpty()) {
                $allowedTerrenoIds = [];
                $results = $results->filter(function (array $row) use (&$allowedTerrenoIds, $auth): bool {
                    $tid = (int) (data_get($row, 'terreno.id') ?? 0);
                    if ($tid <= 0) {
                        return false;
                    }
                    if (! array_key_exists($tid, $allowedTerrenoIds)) {
                        $or = $auth->ensureTerrenoView($tid);
                        $allowedTerrenoIds[$tid] = ! is_string($or);
                    }

                    return $allowedTerrenoIds[$tid];
                })->values();
            }

            if ($results->isEmpty()) {
                return AiToolResponse::empty('Nenhum documento encontrado para a busca.');
            }

            return AiToolResponse::ok([
                'query' => $query,
                'resultados' => $results->values()->all(),
                'meta' => AiToolResponse::listMeta($results->count(), $results->count(), $limit),
            ]);
        } catch (\Exception $e) {
            return AiToolResponse::error('Erro na busca documental: '.$e->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('Texto da busca semântica'),
            'terreno_id' => $schema->integer()->description('Opcional: restringe a busca a um terreno.'),
            'limit' => $schema->integer()->description('Máximo de trechos (padrão 5, máx 20).')->min(1),
        ];
    }
}
