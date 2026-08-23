<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(2)]
#[Timeout(30)]
class QueueHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $queueName,
        public readonly int $dispatchedAt,
    ) {}

    public function handle(): void
    {
        $now = time();
        Cache::put("operations:queue:{$this->queueName}", [
            'consumed_at' => $now,
            'lag_seconds' => max(0, $now - $this->dispatchedAt),
        ], now()->addMinutes(15));
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('Queue heartbeat falhou.', [
            'queue' => $this->queueName,
            'exception' => $exception::class,
        ]);
    }
}
