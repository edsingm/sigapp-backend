<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Database\Factories\Tenant\AiContextRecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $intent
 * @property array<string, mixed>|null $parameters
 * @property array<string, mixed>|null $output
 * @property string $status
 * @property string|null $action
 * @property int $created_by
 * @property int|null $applied_by
 * @property string|null $justification
 * @property Carbon|null $applied_at
 * @property Carbon|null $expires_at
 */
#[Table('ai_context_recommendations')]
#[Fillable(['entity_type', 'entity_id', 'intent', 'parameters', 'input_hash', 'output', 'status', 'action', 'created_by', 'applied_by', 'justification', 'applied_at', 'expires_at'])]
class AiContextRecommendation extends Model
{
    /** @use HasFactory<AiContextRecommendationFactory> */
    use HasFactory;

    protected $casts = [
        'parameters' => 'array',
        'output' => 'array',
        'applied_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
