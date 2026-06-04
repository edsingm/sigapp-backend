<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('status_histories')]
#[Fillable(['terreno_id', 'old_stage', 'old_status_code', 'new_stage', 'new_status_code', 'changed_by', 'reason_code', 'reason', 'metadata_json', 'created_at'])]
/**
 * @property int $id
 * @property int $terreno_id
 * @property string|null $old_stage
 * @property string|null $old_status_code
 * @property string|null $new_stage
 * @property string|null $new_status_code
 * @property int|null $changed_by
 * @property string|null $reason_code
 * @property string|null $reason
 * @property array|null $metadata_json
 * @property Carbon $created_at
 * @property-read User|null $changedBy
 */
class StatusHistory extends Model
{
    use HasFactory, SoftDeletes;

    public const UPDATED_AT = null;

    protected $casts = [
        'metadata_json' => 'array',
        'created_at' => 'datetime',
        'changed_by' => 'int',
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
