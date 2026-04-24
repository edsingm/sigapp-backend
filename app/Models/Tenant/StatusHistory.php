<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatusHistory extends Model
{
    use HasFactory, SoftDeletes;

    public const UPDATED_AT = null;

    protected $table = 'status_histories';

    protected $fillable = [
        'terreno_id',
        'old_stage',
        'old_status_code',
        'new_stage',
        'new_status_code',
        'changed_by',
        'reason_code',
        'reason',
        'metadata_json',
        'created_at',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'created_at' => 'datetime',
        'changed_by' => 'int',
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }
}
