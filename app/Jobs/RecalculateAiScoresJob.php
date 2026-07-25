<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Ai\Tools\AiScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Queue('ai')]
class RecalculateAiScoresJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * O scoring é idempotente (updateOrCreate por terreno), então é seguro
     * reexecutar em falhas transitórias.
     */
    public int $tries = 3;

    public int $timeout = 300;

    /** @var array<int, int> */
    public array $backoff = [60, 180, 300];

    public int $uniqueFor = 660;

    public function uniqueId(): string
    {
        return (string) (tenant()?->getTenantKey() ?? 'central');
    }

    public function handle(AiScoringService $service): void
    {
        $lock = Cache::lock('job:recalculate-ai-scores:'.$this->uniqueId(), 360);
        if (! $lock->get()) {
            return;
        }

        try {
            $service->scoreAll();
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('RecalculateAiScoresJob falhou', [
            'error' => $exception->getMessage(),
        ]);
    }
}
