<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $terreno_id
 * @property string $title
 * @property string $severity
 * @property Carbon|null $due_date
 */
#[Table('comite_pendencias')]
#[Fillable(['comite_revisao_id', 'terreno_id', 'title', 'description', 'severity', 'status', 'department_code', 'responsible_user_id', 'due_date', 'resolved_at'])]
class ComitePendencia extends Model
{
    use HasFactory;

    protected $casts = [
        'due_date' => 'date',
        'resolved_at' => 'datetime',
    ];

    public function comiteRevisao(): BelongsTo
    {
        return $this->belongsTo(ComiteRevisao::class, 'comite_revisao_id');
    }
}
