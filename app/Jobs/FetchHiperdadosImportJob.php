<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Admin\HiperdadosImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Não usa ShouldBeUnique: o processamento em lotes re-despacha a si mesmo.
 * Concorrência é controlada pelo status + arquivo .work.json.
 */
#[Tries(1)]
#[Timeout(600)]
#[Backoff([60])]
#[Queue('exports')]
class FetchHiperdadosImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $importId) {}

    public function handle(HiperdadosImportService $service): void
    {
        $shouldContinue = $service->processFetch($this->importId);

        if ($shouldContinue) {
            self::dispatch($this->importId);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('FetchHiperdadosImportJob falhou', [
            'import_id' => $this->importId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
