<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntityActivity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'entity_activities';

    protected $fillable = [
        'terreno_id',
        'entity_type',
        'entity_id',
        'action',
        'user_id',
        'summary',
        'payload_json',
        'happened_at',
    ];

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
}
