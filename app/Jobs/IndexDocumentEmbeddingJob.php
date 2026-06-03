<?php

namespace App\Jobs;

use App\Models\Tenant\Documento;
use App\Services\AiEmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Tries(3)]
#[Backoff(30)]
#[Timeout(120)]
class IndexDocumentEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $documentId
    ) {}

    public function handle(AiEmbeddingService $embeddingService): void
    {
        $documento = Documento::find($this->documentId);
        if (! $documento) {
            Log::warning("Documento {$this->documentId} não encontrado para indexação.");

            return;
        }

        try {
            $content = $this->extractText($documento);
            if (trim($content) === '') {
                Log::info("Documento {$this->documentId} sem texto extraível, pulando indexação.");

                return;
            }

            $chunksCreated = $embeddingService->indexDocument($this->documentId, $content);

            Log::info("Documento {$this->documentId} indexado com sucesso: {$chunksCreated} chunks criados.");
        } catch (\Exception $e) {
            Log::error("Falha ao indexar documento {$this->documentId}: {$e->getMessage()}");

            throw $e;
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
    protected function extractText(Documento $documento): string
    {
        // Se tem path no storage, tenta ler o conteúdo
        if ($documento->file_path && Storage::exists($documento->file_path)) {
            $ext = strtolower(pathinfo($documento->file_path, PATHINFO_EXTENSION));

            if (in_array($ext, ['txt', 'md', 'csv', 'log', 'json'], true)) {
                return Storage::get($documento->file_path);
            }

            // PDF e outros binários exigem parser — por enquanto usa descrição
            if (in_array($ext, ['pdf', 'doc', 'docx'], true)) {
                // TODO: integrar spatie/laravel-pdf ou similar para extração de texto
                return $documento->descricao ?? $documento->nome;
            }
        }

        // Fallback: usa campos textuais do documento
        $parts = array_filter([
            $documento->nome,
            $documento->descricao,
            $documento->tipo_label ?? $documento->tipo,
            $documento->categoria_label ?? $documento->categoria,
        ]);

        return implode("\n\n", $parts);
    }
}
