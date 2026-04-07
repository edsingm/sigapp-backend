<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiDocumentChunk extends Model
{
    use HasFactory;

    protected $table = 'ai_document_chunks';

    protected $fillable = [
        'document_id',
        'terreno_id',
        'chunk_index',
        'content',
        'metadata',
    ];

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
