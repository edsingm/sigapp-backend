<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NegociacaoEvento extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'negociacao_eventos';

    protected $fillable = [
        'negociacao_id',
        'event_type',
        'payload_json',
        'notes',
        'user_id',
        'happened_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'happened_at' => 'datetime',
        'negociacao_id' => 'int',
        'user_id' => 'int',
    ];

    public function negociacao(): BelongsTo
    {
        return $this->belongsTo(Negociacao::class, 'negociacao_id');
    }
}
