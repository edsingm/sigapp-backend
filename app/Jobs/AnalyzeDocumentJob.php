<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant\DocumentAnalysis;
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
#[Queue('ai')]
class AnalyzeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $analysisId) {}

    public function handle(): void
    {
        $analysis = DocumentAnalysis::query()->find($this->analysisId);
        if (! $analysis || $analysis->status === 'completed') {
            return;
        }
        $analysis->update([
            'status' => 'completed',
            'extracted_fields' => [],
            'limitations' => ['Nenhum provedor OCR foi configurado; a revisão humana continua obrigatória.'],
            'completed_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        DocumentAnalysis::query()->whereKey($this->analysisId)->update([
            'status' => 'failed',
            'error_message' => 'Não foi possível analisar o documento.',
        ]);
        Log::error('AnalyzeDocumentJob falhou definitivamente.', ['analysis_id' => $this->analysisId, 'exception' => $exception::class]);
    }
}
