<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\User;
use Carbon\Carbon;
use Database\Factories\AdminMfaRecoveryCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property Carbon|null $used_at
 */
#[Fillable(['user_id', 'code_hash', 'used_at'])]
#[Hidden(['code_hash'])]
class AdminMfaRecoveryCode extends Model
{
    /** @use HasFactory<AdminMfaRecoveryCodeFactory> */
    use CentralConnection, HasFactory;

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsed(): bool
    {
        return $this->used_at instanceof Carbon;
    }
}
