<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\TerrenoImportException;
use App\Repositories\Tenant\TerrenoImportRepository;
use App\Services\Tenant\TerrenoImportCommitService;
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

#[Tries(3)]
#[Timeout(600)]
#[Backoff([30, 120])]
#[Queue('exports')]
class CommitTerrenoImportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 660;

    public function __construct(public readonly int $importId) {}

    public function uniqueId(): string
    {
        return sprintf('%s:%d', tenant()?->getTenantKey() ?? 'central', $this->importId);
    }

    public function handle(TerrenoImportCommitService $service, TerrenoImportRepository $repository): void
    {
        try {
            $service->commit($this->importId);
        } catch (TerrenoImportException $exception) {
            $repository->markFailed($this->importId, $exception->errorCode, $exception->getMessage());
        }
    }

    public function failed(Throwable $exception): void
    {
        app(TerrenoImportRepository::class)->markFailed(
            $this->importId,
            'TERRAIN_IMPORT_COMMIT_FAILED',
            $exception->getMessage(),
        );
        Log::error('CommitTerrenoImportJob falhou definitivamente.', [
            'import_id' => $this->importId,
            'exception' => $exception::class,
        ]);
    }
}
