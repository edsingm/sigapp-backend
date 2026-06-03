<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Central\WebhookEvent;
use App\Repositories\Contracts\WebhookEventRepositoryInterface;

class WebhookEventRepository implements WebhookEventRepositoryInterface
{
    public function findOrCreate(string $eventId, string $type, array $payload): WebhookEvent
    {
        return WebhookEvent::query()->firstOrCreate(
            ['event_id' => $eventId],
            [
                'type' => $type,
                'payload' => $payload,
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

    public function markAsProcessed(WebhookEvent $event): void
    {
        $event->markAsProcessed();
    }
}
