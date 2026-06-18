<?php

namespace App\Jobs;

use App\Services\AiScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecalculateAiScoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(AiScoringService $service): void
    {
        $service->scoreAll();
    }

    public function failed(Throwable $exception): void
    {
        Log::error('RecalculateAiScoresJob falhou', [
            'error' => $exception->getMessage(),
        ]);
    }
}
