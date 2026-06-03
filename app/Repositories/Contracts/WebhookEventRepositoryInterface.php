<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Central\WebhookEvent;

interface WebhookEventRepositoryInterface
{
    public function findOrCreate(string $eventId, string $type, array $payload): WebhookEvent;

    public function update(WebhookEvent $event, string $type, array $payload): WebhookEvent;

    public function markAsProcessed(WebhookEvent $event): void;
}
