<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools;

use App\Exceptions\AiBudgetExceededException;
use App\Models\Tenant\AiRequestLog;
use App\Repositories\Tenant\AiEmbeddingRepository;
use App\Support\Database\PgVector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use RuntimeException;

class AiEmbeddingService
{
    protected const DEFAULT_MAX_CHUNK_CHARS = 1500;

    public function __construct(
        private readonly AiEmbeddingRepository $repository,
        private readonly AiTelemetryService $telemetry,
    ) {}

    /**
     * Gera embedding de um texto usando o provider configurado no config/ai.php.
     *
     * @return array<int, float|int>
     */
    public function generateEmbedding(string $text): array
    {
        $provider = (string) config('ai.embedding_provider');
        $model = (string) config('ai.embedding_model');
        $startedAt = microtime(true);
        $reservation = null;

        try {
            $reservation = $this->telemetry->reserveBudget([
                'user_id' => Auth::id(),
                'provider' => $provider,
                'model' => $model,
                'tool_calls_count' => 1,
                'tool_calls' => [['tool' => 'embedding.generate']],
            ], (float) config('ai.embedding_budget_reservation_usd', 0.001));

            $response = Embeddings::for([$text])
                ->dimensions(PgVector::DIMENSIONS)
                ->timeout(30)
                ->generate(
                    $provider,
                    $model,
                );

            $embedding = $response->embeddings[0] ?? [];
            if ($embedding === []) {
                throw new RuntimeException('O provider não retornou embedding para o texto informado.');
            }

            /** @var array<int, float|int> $embedding */
            PgVector::assertValid($embedding);

            $this->telemetry->trySettleReservation($reservation, [
                'user_id' => Auth::id(),
                'provider' => $response->meta->provider,
                'model' => $response->meta->model,
                'prompt_tokens' => $response->tokens,
                'completion_tokens' => 0,
                'total_tokens' => $response->tokens,
                'estimated_cost_usd' => $this->telemetry->estimateEmbeddingCost(
                    $response->meta->provider,
                    $response->tokens,
                ),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'tool_calls_count' => 1,
                'tool_calls' => [['tool' => 'embedding.generate']],
                'status' => 'success',
            ]);

            return $embedding;
        } catch (AiBudgetExceededException $exception) {
            throw $exception;
        } catch (\Throwable $e) {
            if ($reservation instanceof AiRequestLog) {
                $this->telemetry->tryFailReservation($reservation, [
                    'user_id' => Auth::id(),
                    'provider' => $provider,
                    'model' => $model,
                    'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                    'tool_calls_count' => 1,
                    'tool_calls' => [['tool' => 'embedding.generate']],
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                ]);
            }

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

        $chunks = $this->chunkText($content);
        $baseMetadata = [
            'tipo' => $documento->tipo,
            'categoria' => $documento->categoria,
            'nome' => $documento->nome,
            ...$metadata,
        ];
        $preparedChunks = [];

        foreach ($chunks as $index => $chunkContent) {
            $embedding = $this->generateEmbedding($chunkContent);
            PgVector::assertValid($embedding);

            $preparedChunks[] = [
                'chunk_index' => $index,
                'content' => $chunkContent,
                'metadata' => $baseMetadata,
                'embedding' => $embedding,
            ];
        }

        return $this->repository->replaceDocumentIndex(
            $documento,
            $preparedChunks,
            (string) config('ai.embedding_provider'),
            (string) config('ai.embedding_model'),
        );
    }

    /**
     * Busca chunks similares a uma query por similaridade de cosseno.
     * PostgreSQL usa pgvector/HNSW; outros drivers calculam na aplicação.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function searchSimilar(string $query, ?int $terrenoId = null, int $limit = 10): Collection
    {
        $startedAt = hrtime(true);
        $limit = max(1, min($limit, 100));
        $queryEmbedding = $this->generateEmbedding($query);
        PgVector::assertValid($queryEmbedding);
        $model = (string) config('ai.embedding_model');

        $nativeEmbeddings = $this->repository->searchSimilarByVector(
            $queryEmbedding,
            $model,
            $terrenoId,
            $limit,
        );
        $usesPgVector = $nativeEmbeddings !== null;

        // O fallback é mantido para SQLite e demais drivers sem pgvector.
        $allEmbeddings = $nativeEmbeddings ?? $this->repository->searchEmbeddings(
            $model,
            $terrenoId,
            200,
        );

        /** @var array<int, array<string, mixed>> $scored */
        $scored = [];

        foreach ($allEmbeddings as $embedding) {
            $similarity = $usesPgVector
                ? (float) $embedding->getAttribute('similarity')
                : $this->storedSimilarity($queryEmbedding, $embedding->getAttribute('embedding'));

            if ($similarity < (float) config('ai.embedding_min_similarity')) {
                continue;
            }

            $chunk = $embedding->chunk;

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

        $results = collect(array_slice($scored, 0, $limit))->values();

        Log::info('AI embedding similarity search completed', [
            'tenant_id' => tenancy()->initialized ? tenant('id') : null,
            'strategy' => $usesPgVector ? 'pgvector_hnsw' : 'php_fallback',
            'model' => $model,
            'terreno_id' => $terrenoId,
            'candidate_count' => $allEmbeddings->count(),
            'result_count' => $results->count(),
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
        ]);

        return $results;
    }

    /**
     * @param  array<int, float|int>  $queryEmbedding
     */
    private function storedSimilarity(array $queryEmbedding, mixed $storedVector): float
    {
        if (! is_array($storedVector) || $storedVector === []) {
            return 0.0;
        }

        /** @var array<int, float|int> $storedVector */
        return $this->cosineSimilarity($queryEmbedding, $storedVector);
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
