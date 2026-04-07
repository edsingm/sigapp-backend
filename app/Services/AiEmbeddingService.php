<?php

namespace App\Services;

use App\Models\Tenant\AiDocumentChunk;
use App\Models\Tenant\AiDocumentEmbedding;
use App\Models\Tenant\Documento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;

class AiEmbeddingService
{
    protected const DEFAULT_MAX_CHUNK_CHARS = 1500;

    protected const DEFAULT_DIMENSIONS = 1536;

    /**
     * Gera embedding de um texto usando o provider configurado no config/ai.php.
     */
    public function generateEmbedding(string $text): array
    {
        try {
            $response = Embeddings::for([$text])
                ->dimensions(self::DEFAULT_DIMENSIONS)
                ->timeout(30)
                ->generate();

            return $response->embeddings[0] ?? [];
        } catch (\Throwable $e) {
            Log::warning("AI Embedding fallback: {$e->getMessage()}");

            // Placeholder para quando o provider não está configurado
            return array_fill(0, self::DEFAULT_DIMENSIONS, 0.0);
        }
    }

    /**
     * Divide texto em chunks de tamanho controlado.
     */
    public function chunkText(string $text, int $maxChars = self::DEFAULT_MAX_CHUNK_CHARS): array
    {
        if (mb_strlen($text) <= $maxChars) {
            return [$text];
        }

        $chunks = [];
        $paragraphs = preg_split('/\n\n+/', $text);
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

                $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph);
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
     */
    public function storeEmbeddings(int $chunkId, array $embedding, ?string $provider = null, ?string $model = null): void
    {
        AiDocumentEmbedding::create([
            'chunk_id' => $chunkId,
            'embedding' => $embedding,
            'provider' => $provider ?? env('AI_EMBEDDING_PROVIDER', 'openai'),
            'model' => $model ?? env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'dimensions' => count($embedding),
        ]);
    }

    /**
     * Indexa um documento completo: chunking + embeddings.
     */
    public function indexDocument(int $documentId, string $content, array $metadata = []): int
    {
        $documento = Documento::find($documentId);
        if (! $documento) {
            throw new \InvalidArgumentException("Documento {$documentId} não encontrado.");
        }

        // Remove chunks antigos
        AiDocumentChunk::where('document_id', $documentId)->delete();

        $chunks = $this->chunkText($content);
        $createdCount = 0;

        foreach ($chunks as $index => $chunkContent) {
            $chunk = AiDocumentChunk::create([
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
     */
    public function searchSimilar(string $query, ?int $terrenoId = null, int $limit = 10): Collection
    {
        $queryEmbedding = $this->generateEmbedding($query);

        // Busca embeddings com filtro opcional por terreno
        $embeddingsQuery = AiDocumentEmbedding::query()
            ->with(['chunk.documento', 'chunk.terreno'])
            ->where('model', env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'));

        if ($terrenoId !== null) {
            $embeddingsQuery->whereHas('chunk', function ($q) use ($terrenoId) {
                $q->where('terreno_id', $terrenoId);
            });
        }

        // Para search sem pgvector, limitamos para não carregar tudo
        $allEmbeddings = $embeddingsQuery
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        // Calcula similaridade de cosseno para cada embedding
        $scored = $allEmbeddings->map(function (AiDocumentEmbedding $embedding) use ($queryEmbedding) {
            $storedVector = $embedding->embedding;
            if (! is_array($storedVector) || empty($storedVector)) {
                return null;
            }

            $similarity = $this->cosineSimilarity($queryEmbedding, $storedVector);

            return [
                'chunk_id' => $embedding->chunk_id,
                'content' => $embedding->chunk->content ?? '',
                'document' => $embedding->chunk->documento ? [
                    'id' => $embedding->chunk->documento->id,
                    'nome' => $embedding->chunk->documento->nome,
                    'tipo' => $embedding->chunk->documento->tipo_label ?? $embedding->chunk->documento->tipo,
                    'categoria' => $embedding->chunk->documento->categoria_label ?? $embedding->chunk->documento->categoria,
                ] : null,
                'terreno' => $embedding->chunk->terreno ? [
                    'id' => $embedding->chunk->terreno->id,
                    'nome' => $embedding->chunk->terreno->nome,
                ] : null,
                'score' => round($similarity, 4),
                'metadata' => $embedding->chunk->metadata,
                'chunk_index' => $embedding->chunk->chunk_index,
            ];
        })->filter();

        // Ordena por score e limita
        return $scored
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Calcula similaridade de cosseno entre dois vetores.
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
