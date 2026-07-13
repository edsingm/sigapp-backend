<?php

namespace App\Services\Ai\Tools;

use App\Repositories\Tenant\AiEmbeddingRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use RuntimeException;

class AiEmbeddingService
{
    protected const DEFAULT_MAX_CHUNK_CHARS = 1500;

    protected const DEFAULT_DIMENSIONS = 1536;

    public function __construct(
        private readonly AiEmbeddingRepository $repository,
    ) {}

    /**
     * Gera embedding de um texto usando o provider configurado no config/ai.php.
     *
     * @return array<int, float|int>
     */
    public function generateEmbedding(string $text): array
    {
        try {
            $response = Embeddings::for([$text])
                ->dimensions(self::DEFAULT_DIMENSIONS)
                ->timeout(30)
                ->generate(
                    (string) config('ai.embedding_provider'),
                    (string) config('ai.embedding_model'),
                );

            $embedding = $response->embeddings[0] ?? [];
            if ($embedding === []) {
                throw new RuntimeException('O provider não retornou embedding para o texto informado.');
            }

            return $embedding;
        } catch (\Throwable $e) {
            Log::warning("AI Embedding generation failed: {$e->getMessage()}");

            throw new RuntimeException('Não foi possível gerar o embedding do documento.', 0, $e);
        }
    }

    /**
     * Divide texto em chunks de tamanho controlado.
     *
     * @return list<string>
     */
    public function chunkText(string $text, int $maxChars = self::DEFAULT_MAX_CHUNK_CHARS): array
    {
        if (mb_strlen($text) <= $maxChars) {
            return [$text];
        }

        $chunks = [];
        $paragraphs = preg_split('/\n\n+/', $text) ?: [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            // Se o parágrafo sozinho excede o limite, dividir por sentenças
            if (mb_strlen($paragraph) > $maxChars) {
                if ($current !== '') {
                    $chunks[] = trim($current);
                    $current = '';
                }

                $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph) ?: [];
                foreach ($sentences as $sentence) {
                    if (mb_strlen($current.' '.$sentence) > $maxChars && $current !== '') {
                        $chunks[] = trim($current);
                        $current = $sentence;
                    } else {
                        $current .= ' '.$sentence;
                    }
                }

                continue;
            }

            // Se adicionar o parágrafo excede o limite, fecha o chunk atual
            if (mb_strlen($current."\n\n".$paragraph) > $maxChars && $current !== '') {
                $chunks[] = trim($current);
                $current = $paragraph;
            } else {
                $current .= ($current === '' ? '' : "\n\n").$paragraph;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    /**
     * Armazena embeddings para um chunk.
     *
     * @param  array<int, float|int>  $embedding
     */
    public function storeEmbeddings(int $chunkId, array $embedding, ?string $provider = null, ?string $model = null): void
    {
        $this->repository->createEmbedding([
            'chunk_id' => $chunkId,
            'embedding' => $embedding,
            'provider' => $provider ?? (string) config('ai.embedding_provider'),
            'model' => $model ?? (string) config('ai.embedding_model'),
            'dimensions' => count($embedding),
        ]);
    }

    /**
     * Indexa um documento completo: chunking + embeddings.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function indexDocument(int $documentId, string $content, array $metadata = []): int
    {
        $documento = $this->repository->findDocumento($documentId);
        if (! $documento) {
            throw new \InvalidArgumentException("Documento {$documentId} não encontrado.");
        }

        // Remove chunks antigos
        $this->repository->deleteChunksByDocument($documentId);

        $chunks = $this->chunkText($content);
        $createdCount = 0;

        foreach ($chunks as $index => $chunkContent) {
            $chunk = $this->repository->createChunk([
                'document_id' => $documentId,
                'terreno_id' => $documento->terreno_id ?? null,
                'chunk_index' => $index,
                'content' => $chunkContent,
                'metadata' => array_merge([
                    'tipo' => $documento->tipo,
                    'categoria' => $documento->categoria,
                    'nome' => $documento->nome,
                ], $metadata),
            ]);

            // Gera e salva embedding
            $embedding = $this->generateEmbedding($chunkContent);
            $this->storeEmbeddings($chunk->id, $embedding);

            $createdCount++;
        }

        return $createdCount;
    }

    /**
     * Busca chunks similares a uma query por similaridade de cosseno.
     * Sem pgvector: carrega vetores e calcula na aplicação.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function searchSimilar(string $query, ?int $terrenoId = null, int $limit = 10): Collection
    {
        $queryEmbedding = $this->generateEmbedding($query);

        // Para search sem pgvector, limitamos para não carregar tudo
        $allEmbeddings = $this->repository->searchEmbeddings(
            (string) config('ai.embedding_model'),
            $terrenoId,
            200,
        );

        /** @var array<int, array<string, mixed>> $scored */
        $scored = [];

        foreach ($allEmbeddings as $embedding) {
            $storedVector = $embedding->getAttribute('embedding');
            if (! is_array($storedVector) || empty($storedVector)) {
                continue;
            }

            $similarity = $this->cosineSimilarity($queryEmbedding, $storedVector);
            if ($similarity < (float) config('ai.embedding_min_similarity')) {
                continue;
            }
            $chunk = $embedding->chunk()->first();

            $this->insertScoredResult($scored, [
                'chunk_id' => $embedding->getAttribute('chunk_id'),
                'content' => $chunk?->content ?? '',
                'document' => $chunk?->documento ? [
                    'id' => $chunk->documento->id,
                    'nome' => $chunk->documento->nome,
                    'tipo' => $chunk->documento->tipo_label ?? $chunk->documento->tipo,
                    'categoria' => $chunk->documento->categoria_label ?? $chunk->documento->categoria,
                ] : null,
                'terreno' => $chunk?->terreno ? [
                    'id' => $chunk->terreno->id,
                    'nome' => $chunk->terreno->nome,
                ] : null,
                'score' => round($similarity, 4),
                'metadata' => $chunk?->metadata,
                'chunk_index' => $chunk?->chunk_index,
            ]);
        }

        return collect(array_slice($scored, 0, $limit))->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<string, mixed>  $result
     */
    private function insertScoredResult(array &$results, array $result): void
    {
        $score = (float) $result['score'];

        foreach ($results as $index => $existing) {
            if ($score > (float) $existing['score']) {
                array_splice($results, $index, 0, [$result]);

                return;
            }
        }

        $results[] = $result;
    }

    /**
     * Calcula similaridade de cosseno entre dois vetores.
     *
     * @param  array<int, float|int>  $a
     * @param  array<int, float|int>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $minLength = min(count($a), count($b));
        if ($minLength === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $minLength; $i++) {
            $dotProduct += ($a[$i] ?? 0) * ($b[$i] ?? 0);
            $normA += pow($a[$i] ?? 0, 2);
            $normB += pow($b[$i] ?? 0, 2);
        }

        $magnitude = sqrt($normA) * sqrt($normB);
        if ($magnitude === 0.0) {
            return 0.0;
        }

        return $dotProduct / $magnitude;
    }
}
