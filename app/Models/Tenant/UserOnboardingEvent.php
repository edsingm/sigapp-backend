<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $event_id
 * @property string $event
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $occurred_at
 */
#[Table('user_onboarding_events')]
#[Fillable(['user_id', 'event_id', 'event', 'metadata', 'occurred_at'])]
class UserOnboardingEvent extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
