<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Admin\HiperdadosImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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

#[Tries(1)]
#[Timeout(600)]
#[Backoff([60])]
#[Queue('exports')]
class CommitHiperdadosImportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 660;

    public function __construct(public readonly int $importId) {}

    public function uniqueId(): string
    {
        return 'hiperdados-commit:'.$this->importId;
    }

    public function handle(HiperdadosImportService $service): void
    {
        $service->processCommit($this->importId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('CommitHiperdadosImportJob falhou', [
            'import_id' => $this->importId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
