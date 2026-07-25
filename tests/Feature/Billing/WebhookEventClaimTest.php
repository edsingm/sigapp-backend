<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\WebhookEventStatus;
use App\Models\Central\WebhookEvent;
use App\Repositories\WebhookEventRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebhookEventClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_worker_claims_an_event_and_a_failed_event_can_be_retried(): void
    {
        $event = WebhookEvent::query()->create([
            'event_id' => 'evt_atomic_claim',
            'type' => 'customer.discount.created',
            'payload' => ['id' => 'evt_atomic_claim'],
            'status' => WebhookEventStatus::PENDING,
        ]);
        $repository = app(WebhookEventRepository::class);

        $this->assertSame(1, $repository->claimForProcessing($event));
        $this->assertNull($repository->claimForProcessing($event));

        $processing = $event->fresh();
        $this->assertInstanceOf(WebhookEvent::class, $processing);
        $this->assertSame(WebhookEventStatus::PROCESSING, $processing->status);
        $this->assertSame(1, $processing->attempts);
        $this->assertNotNull($processing->processing_started_at);

        $repository->markAsFailed($event, 1, 'Falha transitória.');
        $this->assertSame(2, $repository->claimForProcessing($event));
        $repository->markAsProcessed($event, 1);
        $staleAttemptResult = $event->fresh();
        $this->assertInstanceOf(WebhookEvent::class, $staleAttemptResult);
        $this->assertSame(WebhookEventStatus::PROCESSING, $staleAttemptResult->status);
        $repository->markAsProcessed($event, 2);

        $processed = $event->fresh();
        $this->assertInstanceOf(WebhookEvent::class, $processed);
        $this->assertSame(WebhookEventStatus::PROCESSED, $processed->status);
        $this->assertSame(2, $processed->attempts);
        $this->assertNotNull($processed->processed_at);
        $this->assertNull($processed->processing_started_at);
        $this->assertNull($processed->last_error);
    }
}
