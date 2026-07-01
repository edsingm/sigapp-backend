<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property int $user_id
 * @property string $title
 * @property string $body
 * @property string $type
 * @property string|null $entity_type
 * @property string|null $entity_id
 * @property string|null $tenant_slug
 * @property string|null $target_route
 * @property array<string, mixed>|null $payload
 * @property string|null $dedupe_key
 * @property Carbon|null $read_at
 * @property Carbon|null $sent_at
 * @property string|null $delivery_error
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['id', 'user_id', 'title', 'body', 'type', 'entity_type', 'entity_id', 'tenant_slug', 'target_route', 'payload', 'dedupe_key', 'read_at', 'sent_at', 'delivery_error'])]
class MobileNotification extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $notification) {
            if (! $notification->id) {
                $notification->id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
