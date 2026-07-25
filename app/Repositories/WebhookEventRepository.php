<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\WebhookEventStatus;
use App\Models\Central\WebhookEvent;
use App\Repositories\Contracts\WebhookEventRepositoryInterface;
use Illuminate\Support\Str;

class WebhookEventRepository implements WebhookEventRepositoryInterface
{
    private const STALE_AFTER_MINUTES = 10;

    public function findOrCreate(string $eventId, string $type, array $payload): WebhookEvent
    {
        return WebhookEvent::query()->firstOrCreate(
            ['event_id' => $eventId],
            [
                'type' => $type,
                'payload' => $payload,
                'status' => WebhookEventStatus::PENDING,
            ]
        );
    }

    public function update(WebhookEvent $event, string $type, array $payload): WebhookEvent
    {
        $event->forceFill([
            'type' => $type,
            'payload' => $payload,
        ])->save();

        return $event;
    }

    public function claimForProcessing(WebhookEvent $event): ?int
    {
        $claimed = WebhookEvent::query()
            ->whereKey($event->id)
            ->whereNull('processed_at')
            ->where(function ($query): void {
                $query->whereIn('status', [
                    WebhookEventStatus::PENDING,
                    WebhookEventStatus::FAILED,
                ])->orWhere(function ($query): void {
                    $query->where('status', WebhookEventStatus::PROCESSING)
                        ->where(function ($query): void {
                            $query->whereNull('processing_started_at')
                                ->orWhere('processing_started_at', '<=', now()->subMinutes(self::STALE_AFTER_MINUTES));
                        });
                });
            })
            ->increment('attempts', 1, [
                'status' => WebhookEventStatus::PROCESSING,
                'processing_started_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return null;
        }

        $attempt = WebhookEvent::query()
            ->whereKey($event->id)
            ->value('attempts');

        return is_numeric($attempt) ? (int) $attempt : null;
    }

    public function markAsProcessed(WebhookEvent $event, int $attempt): void
    {
        WebhookEvent::query()
            ->whereKey($event->id)
            ->where('status', WebhookEventStatus::PROCESSING)
            ->where('attempts', $attempt)
            ->update([
                'status' => WebhookEventStatus::PROCESSED,
                'processed_at' => now(),
                'processing_started_at' => null,
                'last_error' => null,
                'updated_at' => now(),
            ]);
    }

    public function markAsFailed(WebhookEvent $event, int $attempt, string $error): void
    {
        WebhookEvent::query()
            ->whereKey($event->id)
            ->where('status', WebhookEventStatus::PROCESSING)
            ->where('attempts', $attempt)
            ->update([
                'status' => WebhookEventStatus::FAILED,
                'processing_started_at' => null,
                'last_error' => Str::limit($error, 2000, ''),
                'updated_at' => now(),
            ]);
    }
}
