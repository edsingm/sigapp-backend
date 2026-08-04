<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\AiBudgetExceededException;
use App\Exceptions\DocumentAnalysisUnsupportedException;
use App\Models\Tenant\DocumentAnalysis;
use App\Models\Tenant\Documento;
use App\Services\Ai\Document\DocumentUnderstandingService;
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
#[Timeout(180)]
#[Backoff([10, 60, 120])]
#[Queue('ai')]
class AnalyzeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $analysisId) {}

    public function handle(DocumentUnderstandingService $understanding): void
    {
        $analysis = DocumentAnalysis::query()->find($this->analysisId);
        if (! $analysis || $analysis->status === 'completed') {
            return;
        }

        $documento = Documento::query()->find($analysis->documento_id);
        if (! $documento) {
            $analysis->update([
                'status' => 'failed',
                'error_message' => 'Documento não encontrado para análise.',
                'limitations' => ['Documento ausente no momento da análise.'],
                'completed_at' => now(),
            ]);

            return;
        }

        $analysis->update([
            'status' => 'running',
            'provider' => (string) config('ai.document_provider', 'opencode_go'),
            'model' => (string) config('ai.document_model', 'gpt-5.6-luna'),
            'error_message' => null,
        ]);

        try {
            $result = $understanding->analyze($documento);

            $analysis->update([
                'status' => 'completed',
                'provider' => $result->provider,
                'model' => $result->model,
                'confidence' => $result->confidence,
                'extracted_fields' => $result->extractedFields,
                'limitations' => $result->limitations,
                'error_message' => null,
                'completed_at' => now(),
            ]);

            // Reindex é best-effort: falha de embedding (ex. 401 no provider) NÃO pode
            // marcar a análise como failed nem derrubar o chat (queue sync).
            $this->dispatchEmbeddingReindex((int) $documento->getKey());
        } catch (DocumentAnalysisUnsupportedException $exception) {
            $this->markFailed($analysis, $exception->getMessage(), ['Tipo de arquivo não suportado para análise automática.']);
        } catch (AiBudgetExceededException) {
            $this->markFailed(
                $analysis,
                'Orçamento mensal de IA do tenant esgotado.',
                ['AI_BUDGET_EXCEEDED']
            );
        } catch (Throwable $exception) {
            // Config/auth permanente: não vale retry cego.
            if ($this->isNonRetryable($exception)) {
                $this->markFailed(
                    $analysis,
                    $this->safeClientMessage($exception),
                    [$this->safeClientMessage($exception)]
                );
                Log::error('AnalyzeDocumentJob falhou sem retry (erro permanente).', [
                    'analysis_id' => $this->analysisId,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                return;
            }

            Log::warning('AnalyzeDocumentJob falhou na tentativa atual.', [
                'analysis_id' => $this->analysisId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $analysis = DocumentAnalysis::query()->find($this->analysisId);
        if ($analysis) {
            $safe = $this->safeClientMessage($exception);
            $this->markFailed(
                $analysis,
                $safe,
                [
                    'Falha definitiva após esgotar tentativas do job.',
                    $safe,
                ]
            );
        }

        Log::error('AnalyzeDocumentJob falhou definitivamente.', [
            'analysis_id' => $this->analysisId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    private function isNonRetryable(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'OPENCODE_GO_API_KEY')
            || str_contains($message, 'não configurada')
            || str_contains($message, 'HTTP 401')
            || str_contains($message, 'HTTP 403');
    }

    private function safeClientMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'OPENCODE_GO_API_KEY') || str_contains($message, 'não configurada')) {
            return 'Provedor de análise documental não configurado (OPENCODE_GO_API_KEY).';
        }

        if (str_contains($message, 'HTTP 401') || str_contains($message, 'HTTP 403')) {
            return 'Credencial do provedor de análise documental rejeitada.';
        }

        if (str_contains($message, 'excede o tamanho')) {
            return 'O PDF excede o tamanho máximo permitido para análise.';
        }

        if (str_contains($message, 'não encontrado no storage')) {
            return 'Arquivo do documento não encontrado no storage.';
        }

        return 'Não foi possível analisar o documento.';
    }

    /**
     * @param  list<string>  $limitations
     */
    private function markFailed(DocumentAnalysis $analysis, string $errorMessage, array $limitations): void
    {
        $analysis->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'limitations' => $limitations,
            'completed_at' => now(),
        ]);
    }

    private function dispatchEmbeddingReindex(int $documentId): void
    {
        try {
            IndexDocumentEmbeddingJob::dispatch($documentId);
        } catch (Throwable $exception) {
            Log::warning('Reindex de embedding após análise documental falhou (ignorado).', [
                'document_id' => $documentId,
                'analysis_id' => $this->analysisId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
