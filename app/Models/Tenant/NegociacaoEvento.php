<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NegociacaoEvento extends Model
{
    use HasFactory;

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
    ];

    public function negociacao(): BelongsTo
    {
        return $this->belongsTo(Negociacao::class, 'negociacao_id');
    }
}
