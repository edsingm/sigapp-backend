<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Common\AdminMfaChallengePurpose;
use App\Enums\Common\AdminMfaChallengeStatus;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\AdminMfaChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property int $user_id
 * @property AdminMfaChallengePurpose $purpose
 * @property AdminMfaChallengeStatus $status
 * @property int $factor_version
 * @property string|null $pending_secret
 * @property int $attempts
 * @property Carbon|null $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $invalidated_at
 */
#[Fillable([
    'user_id',
    'token_hash',
    'purpose',
    'status',
    'factor_version',
    'pending_secret',
    'ip_address',
    'user_agent',
    'device_name',
    'attempts',
    'expires_at',
    'consumed_at',
    'invalidated_at',
])]
#[Hidden(['token_hash', 'pending_secret'])]
class AdminMfaChallenge extends Model
{
    /** @use HasFactory<AdminMfaChallengeFactory> */
    use CentralConnection, HasFactory;

    protected function casts(): array
    {
        return [
            'purpose' => AdminMfaChallengePurpose::class,
            'status' => AdminMfaChallengeStatus::class,
            'factor_version' => 'integer',
            'pending_secret' => 'encrypted',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(?Carbon $now = null): bool
    {
        return $this->expires_at === null || $this->expires_at->lessThanOrEqualTo($now ?? now());
    }

    public function isPending(): bool
    {
        return $this->status === AdminMfaChallengeStatus::PENDING;
    }
}
