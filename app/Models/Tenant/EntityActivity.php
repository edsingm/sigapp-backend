<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('entity_activities')]
#[Fillable(['terreno_id', 'entity_type', 'entity_id', 'action', 'user_id', 'summary', 'payload_json', 'happened_at'])]
/**
 * @property int $id
 * @property int $terreno_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $action
 * @property int|null $user_id
 * @property string $summary
 * @property array|null $payload_json
 * @property Carbon $happened_at
 * @property-read User|null $user
 */
class EntityActivity extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'payload_json' => 'array',
        'happened_at' => 'datetime',
        'user_id' => 'int',
        'terreno_id' => 'int',
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
