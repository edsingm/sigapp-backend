<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComiteParecerDepartamento extends Model
{
    use HasFactory;

    protected $table = 'comite_pareceres_departamento';

    protected $fillable = [
        'comite_revisao_id',
        'department_code',
        'reviewer_user_id',
        'decision',
        'comments',
        'checklist_completed',
        'reviewed_at',
    ];

    protected $casts = [
        'checklist_completed' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function comiteRevisao(): BelongsTo
    {
        return $this->belongsTo(ComiteRevisao::class, 'comite_revisao_id');
    }
}
