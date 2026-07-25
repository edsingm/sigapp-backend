<?php

namespace App\Models\Central;

use App\Enums\WebhookEventStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Model para armazenar eventos de webhook processados (idempotência).
 *
 * @property int $id
 * @property string $event_id
 * @property string $type
 * @property array|null $payload
 * @property WebhookEventStatus $status
 * @property int $attempts
 * @property Carbon|null $processing_started_at
 * @property string|null $last_error
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Table('webhook_events')]
#[Fillable(['event_id', 'type', 'payload', 'status', 'attempts', 'processing_started_at', 'last_error', 'processed_at'])]
class WebhookEvent extends Model
{
    use CentralConnection;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookEventStatus::class,
            'attempts' => 'integer',
            'processing_started_at' => 'datetime',
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
}
