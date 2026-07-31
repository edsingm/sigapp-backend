<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\ComiteAiDossier;
use App\Models\Tenant\ComiteRevisao;
use App\Repositories\Tenant\CommitteeAiDossierRepository;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class CommitteeAiDossierPdfService
{
    public function __construct(
        private readonly CommitteeAiDossierRepository $dossiers,
        private readonly CommitteeService $committee,
        private readonly TerrenoExportService $terrenoExport,
    ) {}

    public function download(int $reviewId): mixed
    {
        $review = $this->committee->findOrFail($reviewId);
        $dossier = $this->dossiers->findForReview($review->id);
        if (! $dossier instanceof ComiteAiDossier || $dossier->status !== 'ready') {
            throw new RuntimeException('Dossiê de comitê indisponível ou ainda não gerado.');
        }

        return $this->buildPdf($review, $dossier)
            ->download('dossie-comite-'.$review->id.'.pdf');
    }

    /**
     * @return array{title: string, sections: list<array{key: string, title: string, body: string}>, generatedAt: string}
     */
    public function previewPayload(int $reviewId): array
    {
        $review = $this->committee->findOrFail($reviewId);
        $dossier = $this->dossiers->findForReview($review->id);
        if (! $dossier instanceof ComiteAiDossier) {
            throw new RuntimeException('Dossiê de comitê não encontrado.');
        }

        return [
            'title' => 'Dossiê de comitê #'.$review->id,
            'status' => $dossier->status,
            'sections' => $this->normalizeSections($dossier),
            'generatedAt' => $dossier->generated_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
            'terreno_id' => $dossier->terreno_id,
            'viabilidade_id' => $dossier->viabilidade_id,
        ];
    }

    private function buildPdf(ComiteRevisao $review, ComiteAiDossier $dossier): PdfBuilder
    {
        return Pdf::view('exports.comite-ai-dossier-pdf', [
            'title' => 'Dossiê de comitê #'.$review->id,
            'review' => $review,
            'dossier' => $dossier,
            'sections' => $this->normalizeSections($dossier),
            'generatedAt' => now()->format('d/m/Y H:i'),
            'terrenoId' => $dossier->terreno_id,
            'viabilidadeId' => $dossier->viabilidade_id,
        ])
            ->format('a4')
            ->withBrowsershot(function ($browsershot): void {
                $this->terrenoExport->applyBrowsershotDefaults($browsershot);
            });
    }

    /**
     * @return list<array{key: string, title: string, body: string}>
     */
    private function normalizeSections(ComiteAiDossier $dossier): array
    {
        $sections = is_array($dossier->sections) ? $dossier->sections : [];
        $normalized = [];
        foreach ($sections as $key => $value) {
            $keyString = is_string($key) ? $key : (string) $key;
            $normalized[] = [
                'key' => $keyString,
                'title' => strtoupper(str_replace('_', ' ', $keyString)),
                'body' => is_string($value)
                    ? $value
                    : (is_scalar($value) || $value === null
                        ? (string) $value
                        : (string) json_encode($value, JSON_UNESCAPED_UNICODE)),
            ];
        }

        return $normalized;
    }
}
