<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComitePendencia extends Model
{
    use HasFactory;

    protected $table = 'comite_pendencias';

    protected $fillable = [
        'comite_revisao_id',
        'terreno_id',
        'title',
        'description',
        'severity',
        'status',
        'department_code',
        'responsible_user_id',
        'due_date',
        'resolved_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'resolved_at' => 'datetime',
    ];

    public function comiteRevisao(): BelongsTo
    {
        return $this->belongsTo(ComiteRevisao::class, 'comite_revisao_id');
    }
}
