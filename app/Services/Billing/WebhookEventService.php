<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Central\WebhookEvent;
use App\Repositories\Contracts\WebhookEventRepositoryInterface;

class WebhookEventService
{
    public function __construct(
        private readonly WebhookEventRepositoryInterface $repository,
    ) {}

    public function findOrCreate(string $eventId, string $type, array $payload): WebhookEvent
    {
        return $this->repository->findOrCreate($eventId, $type, $payload);
    }

    public function update(WebhookEvent $event, string $type, array $payload): WebhookEvent
    {
        return $this->repository->update($event, $type, $payload);
    }

    public function claimForProcessing(WebhookEvent $event): ?int
    {
        return $this->repository->claimForProcessing($event);
    }

    public function markAsProcessed(WebhookEvent $event, int $attempt): void
    {
        $this->repository->markAsProcessed($event, $attempt);
    }

    public function markAsFailed(WebhookEvent $event, int $attempt, string $error): void
    {
        $this->repository->markAsFailed($event, $attempt, $error);
    }
}
