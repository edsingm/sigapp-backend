<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\AiDocumentChunk;
use App\Models\Tenant\AiDocumentEmbedding;
use App\Models\Tenant\Documento;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AiEmbeddingRepository
{
    public function findDocumento(int $id): ?Documento
    {
        return Documento::find($id);
    }

    /**
     * @param  list<array{
     *     chunk_index: int,
     *     content: string,
     *     metadata: array<string, mixed>,
     *     embedding: array<int, float|int>
     * }>  $chunks
     */
    public function replaceDocumentIndex(
        Documento $documento,
        array $chunks,
        string $provider,
        string $model,
    ): int {
        return DB::transaction(function () use ($documento, $chunks, $provider, $model): int {
            AiDocumentChunk::query()
                ->where('document_id', $documento->id)
                ->delete();

            foreach ($chunks as $chunkData) {
                $chunk = AiDocumentChunk::query()->create([
                    'document_id' => $documento->id,
                    'terreno_id' => $documento->terreno_id,
                    'chunk_index' => $chunkData['chunk_index'],
                    'content' => $chunkData['content'],
                    'metadata' => $chunkData['metadata'],
                ]);

                AiDocumentEmbedding::query()->create([
                    'chunk_id' => $chunk->id,
                    'embedding' => $chunkData['embedding'],
                    'provider' => $provider,
                    'model' => $model,
                    'dimensions' => count($chunkData['embedding']),
                ]);
            }

            return count($chunks);
        });
    }

    /**
     * Busca embeddings por modelo, filtrando opcionalmente por terreno.
     * Limitado para evitar carregamento excessivo (sem pgvector).
     *
     * @return Collection<int, AiDocumentEmbedding>
     */
    public function searchEmbeddings(string $model, ?int $terrenoId, int $limit): Collection
    {
        $query = AiDocumentEmbedding::query()
            ->with(['chunk.documento', 'chunk.terreno'])
            ->where('model', $model);

        if ($terrenoId !== null) {
            $query->whereHas('chunk', fn ($q) => $q->where('terreno_id', $terrenoId));
        }

        return $query
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
