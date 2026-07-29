<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AiReportGenerationStatus;
use App\Models\Tenant\AiReportGeneration;
use App\Repositories\Tenant\AiReportGenerationRepository;
use App\Repositories\Tenant\TerrenoRepository;
use App\Services\Ai\Tools\CreatePdfsTool;
use App\Services\Tenant\TerrenoAiReportService;
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
use Laravel\Ai\Tools\Request as AiToolRequest;
use RuntimeException;
use Throwable;

#[Tries(3)]
#[Timeout(240)]
#[Backoff([30, 120, 300])]
#[Queue('ai')]
class GenerateTerrenoAiReportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $generationId) {}

    public int $uniqueFor = 660;

    public function uniqueId(): string
    {
        return sprintf('%s:%d', tenant()?->getTenantKey() ?? 'central', $this->generationId);
    }

    public function handle(
        AiReportGenerationRepository $generationRepository,
        TerrenoRepository $terrenoRepository,
        TerrenoAiReportService $reportService,
        CreatePdfsTool $pdfTool,
    ): void {
        $generation = $generationRepository->claimQueued($this->generationId);
        if (! $generation instanceof AiReportGeneration) {
            return;
        }

        try {
            $terreno = $terrenoRepository->findById($generation->terreno_id);
            if (! $terreno) {
                throw new RuntimeException('Terreno não encontrado para a geração do relatório.');
            }

            $terreno = $terrenoRepository->loadDetailRelations($terreno);
            $report = $reportService->build($terreno);

            $generationRepository->update($generation, ['progress' => 70]);

            $pdfResult = $pdfTool->handle(new AiToolRequest([
                'filename' => $report['filename'],
                'title' => $report['title'],
                'html_content' => $report['html_content'],
                'terreno_id' => $terreno->id,
                // Job já validou o terreno na API; fila não tem sessão de usuário.
                '_auth_checked' => true,
                '_skip_rate_limit' => true,
            ]));

            $pdfOk = is_string($pdfResult) && (
                str_contains($pdfResult, 'PDF gerado com sucesso')
                || str_contains($pdfResult, '"code":"OK"')
            );

            if (! $pdfOk) {
                throw new RuntimeException(is_string($pdfResult) ? $pdfResult : 'Falha ao gerar relatório em PDF.');
            }

            $generatedReport = $pdfTool->lastGeneratedReport();
            if ($generatedReport === null
                || (int) $generatedReport->getAttribute('terreno_id') !== (int) $terreno->id) {
                throw new RuntimeException('PDF gerado, mas o registro não pertence ao terreno solicitado.');
            }

            $generationRepository->update($generation, [
                'status' => AiReportGenerationStatus::COMPLETED,
                'progress' => 100,
                'report_id' => $generatedReport->getKey(),
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $generationRepository->releaseForRetry($this->generationId);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        app(AiReportGenerationRepository::class)->markFailed($this->generationId);

        Log::error('GenerateTerrenoAiReportJob falhou definitivamente.', [
            'generation_id' => $this->generationId,
            'exception' => $exception::class,
        ]);
    }
}
