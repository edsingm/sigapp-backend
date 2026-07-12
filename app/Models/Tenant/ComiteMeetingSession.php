<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Database\Factories\Tenant\ComiteMeetingSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $comite_revisao_id
 * @property string $title
 * @property Carbon $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property string $status
 * @property string $meeting_mode
 * @property string|null $location
 * @property int|null $chair_user_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $chair
 * @property-read ComiteMeetingMinutes|null $minutes
 */
#[Table('comite_meeting_sessions')]
#[Fillable(['comite_revisao_id', 'title', 'scheduled_at', 'started_at', 'ended_at', 'status', 'meeting_mode', 'location', 'chair_user_id', 'notes', 'created_by', 'updated_by'])]
class ComiteMeetingSession extends Model
{
    /** @use HasFactory<ComiteMeetingSessionFactory> */
    use HasFactory;

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /** @return BelongsTo<ComiteRevisao, $this> */
    public function comiteRevisao(): BelongsTo
    {
        return $this->belongsTo(ComiteRevisao::class, 'comite_revisao_id');
    }

    /** @return BelongsTo<User, $this> */
    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chair_user_id');
    }

    /** @return HasMany<ComiteMeetingAgendaItem, $this> */
    public function agendaItems(): HasMany
    {
        $relation = $this->hasMany(ComiteMeetingAgendaItem::class, 'session_id');

        return $relation;
    }

    /** @return HasMany<ComiteMeetingParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ComiteMeetingParticipant::class, 'session_id');
    }

    /** @return HasOne<ComiteMeetingMinutes, $this> */
    public function minutes(): HasOne
    {
        return $this->hasOne(ComiteMeetingMinutes::class, 'session_id');
    }
}
