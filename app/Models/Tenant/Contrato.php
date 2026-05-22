<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('contratos')]
#[Fillable(['terreno_id', 'negociacao_id', 'contract_type', 'contract_number', 'signed_at', 'start_date', 'end_date', 'status', 'file_path', 'notes', 'created_by', 'updated_by'])]
class Contrato extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'signed_at' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }

    public function negociacao(): BelongsTo
    {
        return $this->belongsTo(Negociacao::class, 'negociacao_id');
    }

    public function partes(): HasMany
    {
        return $this->hasMany(ContratoParte::class, 'contrato_id');
    }
}
