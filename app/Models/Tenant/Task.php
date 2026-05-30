<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['terreno_id', 'related_type', 'related_id', 'title', 'description', 'assigned_to', 'status', 'priority', 'due_date', 'completed_at', 'created_by', 'updated_by'])]
/**
 * @property int $id
 * @property int|null $terreno_id
 * @property string $title
 * @property string $status
 * @property string|null $priority
 * @property int|null $assigned_to
 * @property \Carbon\Carbon|null $due_date
 */
class Task extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Terreno, $this>
     */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
