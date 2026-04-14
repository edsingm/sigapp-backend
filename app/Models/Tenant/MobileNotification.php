<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MobileNotification extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'title',
        'body',
        'type',
        'entity_type',
        'entity_id',
        'tenant_slug',
        'target_route',
        'payload',
        'dedupe_key',
        'read_at',
        'sent_at',
        'delivery_error',
    ];

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
