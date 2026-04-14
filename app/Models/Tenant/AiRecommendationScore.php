<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRecommendationScore extends Model
{
    use HasFactory;

    protected $table = 'ai_recommendation_scores';

    protected $fillable = [
        'terreno_id',
        'score',
        'tier',
        'factors',
        'version',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'factors' => 'array',
        'version' => 'integer',
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }

    public function scopeByTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('tier', ['alta_prioridade', 'atencao'])
            ->orderByDesc('score');
    }

    public function scopeByTerreno($query, int $terrenoId)
    {
        return $query->where('terreno_id', $terrenoId);
    }
}
