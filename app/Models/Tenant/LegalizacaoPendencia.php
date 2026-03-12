<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalizacaoPendencia extends Model
{
    use HasFactory;

    protected $table = 'legalizacao_pendencias';

    protected $fillable = [
        'legalizacao_id',
        'legalizacao_etapa_id',
        'title',
        'severity',
        'status',
        'is_critical',
        'responsible_user_id',
        'due_date',
        'resolved_at',
        'notes',
    ];

    protected $casts = [
        'is_critical' => 'boolean',
        'due_date' => 'date',
        'resolved_at' => 'datetime',
    ];

    public function legalizacao(): BelongsTo
    {
        return $this->belongsTo(Legalizacao::class, 'legalizacao_id');
    }
}
