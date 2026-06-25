<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $chunk_id
 * @property array<int, float|int>|null $embedding
 * @property AiDocumentChunk|null $chunk
 */
#[Table('ai_document_embeddings')]
#[Fillable(['chunk_id', 'embedding', 'provider', 'model', 'dimensions'])]
class AiDocumentEmbedding extends Model
{
    protected $casts = [
        'embedding' => 'array',
        'dimensions' => 'integer',
    ];

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(AiDocumentChunk::class, 'chunk_id');
    }
}
