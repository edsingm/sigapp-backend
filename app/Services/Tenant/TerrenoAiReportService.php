<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Support\Str;

class TerrenoAiReportService
{
    public function __construct(
        private readonly TerrenoAiReportDataService $dataService,
        private readonly TerrenoAiNarrativeService $narrativeService,
    ) {}

    /**
     * @return array{
     *   title: string,
     *   filename: string,
     *   html_content: string
     * }
     */
    public function build(Terreno $terreno): array
    {
        $reportData = $this->dataService->collect($terreno);
        $aiNarrative = $this->narrativeService->generate($reportData['context']);

        return [
            'title' => "Relatório SIG IA do Terreno #{$terreno->id}",
            'filename' => Str::slug("relatorio-sig-ia-terreno-{$terreno->id}-{$terreno->nome}"),
            'html_content' => $this->composeHtmlContent($aiNarrative['html'], $reportData['map_data_uri']),
        ];
    }

    private function composeHtmlContent(string $aiNarrativeHtml, string $mapDataUri): string
    {
        $parts = [];

        if (trim($aiNarrativeHtml) !== '') {
            $parts[] = $aiNarrativeHtml;
        }

        if ($mapDataUri !== '') {
            $escapedMap = htmlspecialchars($mapDataUri, ENT_QUOTES, 'UTF-8');
            $parts[] = <<<HTML
<h2>Mapa do Polígono</h2>
<img
    src="{$escapedMap}"
    alt="Mapa estático do polígono do terreno"
    style="width: 100%; border: 1px solid #d5e2da; border-radius: 10px;"
>
<p>Mapa gerado a partir do polígono cadastrado, com o centroide e os pontos de apoio destacados.</p>
HTML;
        }

        return implode("\n", $parts);
    }
}
