<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\AiDocumentChunk;
use App\Models\Tenant\AiDocumentEmbedding;
use App\Models\Tenant\Documento;
use Illuminate\Database\Eloquent\Collection;

class AiEmbeddingRepository
{
    public function findDocumento(int $id): ?Documento
    {
        return Documento::find($id);
    }

    public function deleteChunksByDocument(int $documentId): void
    {
        AiDocumentChunk::where('document_id', $documentId)->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createChunk(array $data): AiDocumentChunk
    {
        return AiDocumentChunk::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createEmbedding(array $data): AiDocumentEmbedding
    {
        return AiDocumentEmbedding::create($data);
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
