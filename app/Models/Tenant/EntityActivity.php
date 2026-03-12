<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityActivity extends Model
{
    use HasFactory;

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
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }
}
