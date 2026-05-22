<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('ai_document_chunks')]
#[Fillable(['document_id', 'terreno_id', 'chunk_index', 'content', 'metadata'])]
class AiDocumentChunk extends Model
{
    use HasFactory;

    protected $casts = [
        'metadata' => 'array',
        'chunk_index' => 'integer',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'document_id');
    }

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }

    public function embeddings(): HasMany
    {
        return $this->hasMany(AiDocumentEmbedding::class, 'chunk_id');
    }
}
