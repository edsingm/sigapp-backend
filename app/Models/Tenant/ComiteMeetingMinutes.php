<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Database\Factories\Tenant\ComiteMeetingMinutesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $session_id
 * @property string|null $summary
 * @property array<int, mixed>|null $decisions
 * @property array<int, mixed>|null $blockers
 * @property string|null $next_steps
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
#[Table('comite_meeting_minutes')]
#[Fillable(['session_id', 'summary', 'decisions', 'blockers', 'next_steps', 'approved_by', 'approved_at'])]
class ComiteMeetingMinutes extends Model
{
    /** @use HasFactory<ComiteMeetingMinutesFactory> */
    use HasFactory;

    protected $casts = [
        'decisions' => 'array',
        'blockers' => 'array',
        'approved_at' => 'datetime',
    ];

    /** @return BelongsTo<ComiteMeetingSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ComiteMeetingSession::class, 'session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
