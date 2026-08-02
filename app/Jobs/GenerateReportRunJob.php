<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\StorageQuotaExceededException;
use App\Repositories\Tenant\ReportRunRepository;
use App\Services\Tenant\ReportGenerationService;
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
#[Timeout(120)]
#[Backoff([10, 60])]
#[Queue('exports')]
class GenerateReportRunJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $runId,
        private readonly ?string $uniqueKey = null,
    ) {}

    public int $uniqueFor = 660;

    public function uniqueId(): string
    {
        return sprintf(
            '%s:%s',
            tenant()?->getTenantKey() ?? 'central',
            $this->uniqueKey ?? (string) $this->runId,
        );
    }

    public function handle(ReportRunRepository $repository, ReportGenerationService $service): void
    {
        $run = $repository->claimPending($this->runId);
        if ($run === null) {
            return;
        }

        try {
            $service->generate($run);
        } catch (StorageQuotaExceededException) {
            $repository->markFailed($this->runId);
        } catch (Throwable $exception) {
            $repository->releaseForRetry($this->runId);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        app(ReportRunRepository::class)->markFailed($this->runId);
        Log::error('GenerateReportRunJob falhou definitivamente.', [
            'run_id' => $this->runId,
            'exception' => $exception::class,
        ]);
    }
}
