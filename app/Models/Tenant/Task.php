<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['terreno_id', 'related_type', 'related_id', 'title', 'description', 'assigned_to', 'status', 'priority', 'tags', 'due_date', 'completed_at', 'created_by', 'updated_by'])]
/**
 * @property int $id
 * @property int|null $terreno_id
 * @property string $title
 * @property string $status
 * @property string|null $priority
 * @property int|null $assigned_to
 * @property Carbon|null $due_date
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
        'tags' => 'array',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Comentários são armazenados na tabela compartilhada de comentários.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        $relation = $this->hasMany(Comment::class, 'related_id');
        $relation->where('related_type', 'task');

        return $relation;
    }

    /**
     * @return BelongsToMany<Task, $this>
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'task_dependencies',
            'task_id',
            'depends_on_task_id',
        );
    }
}
