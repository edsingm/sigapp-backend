<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant\TenantExportGeneration;
use App\Repositories\Tenant\TenantExportGenerationRepository;
use App\Services\Tenant\TenantExportGenerator;
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
#[Timeout(300)]
#[Backoff([30, 120])]
#[Queue('exports')]
class GenerateTenantExportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $generationId) {}

    public int $uniqueFor = 660;

    public function uniqueId(): string
    {
        return sprintf('%s:%d', tenant()?->getTenantKey() ?? 'central', $this->generationId);
    }

    public function handle(
        TenantExportGenerationRepository $repository,
        TenantExportGenerator $generator,
    ): void {
        $generation = $repository->claimQueued($this->generationId);
        if (! $generation instanceof TenantExportGeneration) {
            return;
        }

        try {
            $repository->markCompleted($generation, $generator->generate($generation));
        } catch (Throwable $exception) {
            $repository->releaseForRetry($this->generationId);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        app(TenantExportGenerationRepository::class)->markFailed($this->generationId);

        Log::error('GenerateTenantExportJob falhou definitivamente.', [
            'generation_id' => $this->generationId,
            'exception' => $exception::class,
        ]);
    }
}
