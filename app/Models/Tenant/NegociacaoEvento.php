<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('negociacao_eventos')]
#[Fillable(['negociacao_id', 'event_type', 'payload_json', 'notes', 'user_id', 'happened_at'])]
class NegociacaoEvento extends Model
{
    use HasFactory, SoftDeletes;

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
