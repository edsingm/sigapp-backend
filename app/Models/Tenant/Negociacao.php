<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Negociacao extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'negociacoes';

    protected $fillable = [
        'terreno_id',
        'status',
        'proposal_value',
        'business_model',
        'started_at',
        'closed_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'proposal_value' => 'decimal:2',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(NegociacaoEvento::class, 'negociacao_id');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'negociacao_id');
    }
}
