<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant\ReportRun;
use App\Services\Tenant\ReportGenerationService;
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

#[Tries(3)]
#[Timeout(120)]
#[Backoff([10, 60])]
#[Queue('exports')]
class GenerateReportRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $runId) {}

    public function handle(ReportGenerationService $service): void
    {
        $run = ReportRun::query()->find($this->runId);
        if (! $run || $run->status === 'completed') {
            return;
        }

        $service->generate($run);
    }

    public function failed(Throwable $exception): void
    {
        ReportRun::query()->whereKey($this->runId)->update([
            'status' => 'failed',
            'progress' => 0,
            'error_message' => 'Não foi possível gerar o relatório. Tente novamente.',
        ]);
        Log::error('GenerateReportRunJob falhou definitivamente.', [
            'run_id' => $this->runId,
            'exception' => $exception::class,
        ]);
    }
}
