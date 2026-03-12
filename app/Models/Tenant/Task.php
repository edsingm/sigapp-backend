<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'terreno_id',
        'related_type',
        'related_id',
        'title',
        'description',
        'assigned_to',
        'status',
        'priority',
        'due_date',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
