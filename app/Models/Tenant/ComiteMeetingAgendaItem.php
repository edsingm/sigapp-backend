<?php

namespace App\Models\Tenant;

use Database\Factories\Tenant\ComiteMeetingAgendaItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $session_id
 * @property string $title
 * @property string|null $description
 * @property int $position
 * @property int|null $duration_minutes
 * @property bool $decision_required
 * @property string $status
 */
#[Table('comite_meeting_agenda_items')]
#[Fillable(['session_id', 'title', 'description', 'position', 'duration_minutes', 'decision_required', 'status'])]
class ComiteMeetingAgendaItem extends Model
{
    /** @use HasFactory<ComiteMeetingAgendaItemFactory> */
    use HasFactory;

    protected $casts = [
        'position' => 'integer',
        'duration_minutes' => 'integer',
        'decision_required' => 'boolean',
    ];

    /** @return BelongsTo<ComiteMeetingSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ComiteMeetingSession::class, 'session_id');
    }
}
