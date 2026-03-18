<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Model para armazenar eventos de webhook processados (idempotência).
 *
 * @property int $id
 * @property string $event_id
 * @property string $type
 * @property array|null $payload
 * @property \Carbon\Carbon|null $processed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class WebhookEvent extends Model
{
    use CentralConnection;

    protected $table = 'webhook_events';

    protected $fillable = [
        'event_id',
        'type',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Verifica se o evento já foi processado.
     */
    public static function wasProcessed(string $eventId): bool
    {
        return static::where('event_id', $eventId)
            ->whereNotNull('processed_at')
            ->exists();
    }

    /**
     * Marca o evento como processado.
     */
    public function markAsProcessed(): self
    {
        $this->update(['processed_at' => now()]);

        return $this;
    }
}
