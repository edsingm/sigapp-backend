<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant\Documento;
use App\Services\Ai\Tools\AiEmbeddingService;
use App\Services\Tenant\DocumentoService;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Tries(3)]
#[Backoff(30)]
#[Timeout(120)]
#[Queue('ai')]
class IndexDocumentEmbeddingJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $documentId
    ) {}

    public int $uniqueFor = 660;

    public function uniqueId(): string
    {
        return sprintf('%s:%d', tenant()?->getTenantKey() ?? 'central', $this->documentId);
    }

    public function handle(AiEmbeddingService $embeddingService, DocumentoService $documentoService): void
    {
        $lock = Cache::lock('job:index-document-embedding:'.$this->uniqueId(), 180);
        if (! $lock->get()) {
            Log::info("Documento {$this->documentId} já está sendo indexado.");

            return;
        }

        try {
            $documento = Documento::find($this->documentId);
            if (! $documento) {
                Log::warning("Documento {$this->documentId} não encontrado para indexação.");

                return;
            }

            $content = $this->extractText($documento, $documentoService);
            if (trim($content) === '') {
                Log::info("Documento {$this->documentId} sem texto extraível, pulando indexação.");

                return;
            }

            $chunksCreated = $embeddingService->indexDocument($this->documentId, $content);

            Log::info("Documento {$this->documentId} indexado com sucesso: {$chunksCreated} chunks criados.");
        } catch (\Exception $e) {
            Log::error("Falha ao indexar documento {$this->documentId}: {$e->getMessage()}");

            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Trata falha definitiva do job após esgotar tentativas.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('IndexDocumentEmbeddingJob falhou definitivamente', [
            'document_id' => $this->documentId,
            'error' => $exception->getMessage(),
            'exception_class' => $exception::class,
        ]);
    }

    /**
     * Extrai texto do documento.
     */
    protected function extractText(Documento $documento, DocumentoService $documentoService): string
    {
        $analysisText = $this->textFromCompletedAnalysis($documento);
        if ($analysisText !== '') {
            return $analysisText;
        }

        // Se tem path no storage, tenta ler o conteúdo
        $disk = Storage::disk($documentoService->storageDisk());
        if ($documento->file_path && $disk->exists($documento->file_path)) {
            $ext = strtolower(pathinfo($documento->file_path, PATHINFO_EXTENSION));

            if (in_array($ext, ['txt', 'md', 'csv', 'log', 'json'], true)) {
                return $disk->get($documento->file_path);
            }

            // PDF/Office sem análise: fallback a metadados (conteúdo binário não é indexado aqui)
            if (in_array($ext, ['pdf', 'doc', 'docx'], true)) {
                return $this->fallbackMetadataText($documento);
            }
        }

        return $this->fallbackMetadataText($documento);
    }

    protected function textFromCompletedAnalysis(Documento $documento): string
    {
        $analysis = $documento->analyses()
            ->where('status', 'completed')
            ->latest('id')
            ->first();

        if ($analysis === null) {
            return '';
        }

        $fields = is_array($analysis->extracted_fields) ? $analysis->extracted_fields : [];
        $summary = is_string($fields['summary'] ?? null) ? trim($fields['summary']) : '';
        $keyFields = is_array($fields['key_fields'] ?? null) ? $fields['key_fields'] : [];

        $parts = array_filter([
            $documento->nome,
            $summary,
            $keyFields !== [] ? json_encode($keyFields, JSON_UNESCAPED_UNICODE) : null,
        ], static fn ($part): bool => is_string($part) && $part !== '');

        return implode("\n\n", $parts);
    }

    protected function fallbackMetadataText(Documento $documento): string
    {
        $parts = array_filter([
            $documento->nome,
            $documento->descricao,
            $documento->tipo_label ?? $documento->tipo,
            $documento->categoria_label ?? $documento->categoria,
        ]);

        return implode("\n\n", $parts);
    }
}
