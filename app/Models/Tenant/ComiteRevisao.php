<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComiteRevisao extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'comite_revisoes';

    protected $fillable = [
        'terreno_id',
        'viabilidade_id',
        'status',
        'final_decision',
        'final_comments',
        'required_departments',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'required_departments' => 'array',
        'decided_at' => 'datetime',
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }

    public function viabilidade(): BelongsTo
    {
        return $this->belongsTo(Viabilidade::class, 'viabilidade_id');
    }

    public function pareceresDepartamento(): HasMany
    {
        return $this->hasMany(ComiteParecerDepartamento::class, 'comite_revisao_id');
    }

    public function pendencias(): HasMany
    {
        return $this->hasMany(ComitePendencia::class, 'comite_revisao_id');
    }
}
