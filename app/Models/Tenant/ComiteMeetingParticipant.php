<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Database\Factories\Tenant\ComiteMeetingParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $session_id
 * @property int|null $user_id
 * @property string|null $name
 * @property string|null $email
 * @property string $role
 * @property string $attendance_status
 * @property Carbon|null $joined_at
 * @property-read User|null $user
 */
#[Table('comite_meeting_participants')]
#[Fillable(['session_id', 'user_id', 'name', 'email', 'role', 'attendance_status', 'joined_at'])]
class ComiteMeetingParticipant extends Model
{
    /** @use HasFactory<ComiteMeetingParticipantFactory> */
    use HasFactory;

    protected $casts = ['joined_at' => 'datetime'];

    /** @return BelongsTo<ComiteMeetingSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ComiteMeetingSession::class, 'session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
