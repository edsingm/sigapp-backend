<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDocumentEmbedding extends Model
{
    protected $table = 'ai_document_embeddings';

    protected $fillable = [
        'chunk_id',
        'embedding',
        'provider',
        'model',
        'dimensions',
    ];

    protected $casts = [
        'embedding' => 'array',
        'dimensions' => 'integer',
    ];

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(AiDocumentChunk::class, 'chunk_id');
    }
}
