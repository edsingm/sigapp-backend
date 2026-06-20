<?php

namespace App\Services\Tenant;

use App\Services\Ai\Tools\CompareAreasTool;
use App\Services\Ai\Tools\DetectAnomaliesTool;
use App\Services\Ai\Tools\EstimateVgvTool;
use App\Services\Ai\Tools\GenerateInsightsTool;
use App\Services\Ai\Tools\GetCityIbgeProfileTool;
use App\Services\Ai\Tools\GetComiteTool;
use App\Services\Ai\Tools\GetDashboardSummaryTool;
use App\Services\Ai\Tools\GetDocumentosTool;
use App\Services\Ai\Tools\GetLegalizacaoTool;
use App\Services\Ai\Tools\GetNegociacaoTool;
use App\Services\Ai\Tools\GetRankingTool;
use App\Services\Ai\Tools\GetTasksTool;
use App\Services\Ai\Tools\GetTerrenoDetailsTool;
use App\Services\Ai\Tools\GetTerrenoGeoAnalysisTool;
use App\Services\Ai\Tools\GetTerrenoScoreTool;
use App\Services\Ai\Tools\GetTrendsTool;
use App\Services\Ai\Tools\GetViabilidadesTool;
use App\Services\Ai\Tools\PredictViabilityTool;
use App\Services\Ai\Tools\ProactiveMonitorTool;
use App\Models\Tenant\Terreno;
use App\Services\Ai\Tools\AiProviderRouter;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request as AiToolRequest;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use RuntimeException;
use Stringable;

class TerrenoAiReportService
{
    /**
     * @return array{
     *   title: string,
     *   filename: string,
     *   html_content: string
     * }
     */
    public function build(Terreno $terreno): array
    {
        $detailTool = app(GetTerrenoDetailsTool::class);
        $geoTool = app(GetTerrenoGeoAnalysisTool::class);
        $viabilidadesTool = app(GetViabilidadesTool::class);
        $legalizacaoTool = app(GetLegalizacaoTool::class);
        $comiteTool = app(GetComiteTool::class);
        $negociacaoTool = app(GetNegociacaoTool::class);
        $documentosTool = app(GetDocumentosTool::class);
        $tasksTool = app(GetTasksTool::class);
        $scoreTool = app(GetTerrenoScoreTool::class);
        $rankingTool = app(GetRankingTool::class);
        $predictTool = app(PredictViabilityTool::class);
        $vgvTool = app(EstimateVgvTool::class);
        $dashboardTool = app(GetDashboardSummaryTool::class);
        $monitorTool = app(ProactiveMonitorTool::class);
        $anomaliesTool = app(DetectAnomaliesTool::class);
        $insightsTool = app(GenerateInsightsTool::class);
        $trendsTool = app(GetTrendsTool::class);
        $compareTool = app(CompareAreasTool::class);
        $cityTool = app(GetCityIbgeProfileTool::class);

        $detail = $this->toolArray(
            $detailTool->handle(new AiToolRequest([
                'terreno_id' => $terreno->id,
                'include_viabilidades' => true,
            ]))
        );

        $geo = $this->toolArray(
            $geoTool->handle(new AiToolRequest([
                'terreno_id' => $terreno->id,
                'radius_metros' => 1000,
            ]))
        );

        $viabilidades = $this->toolArray(
            $viabilidadesTool->handle(new AiToolRequest([
                'terreno_id' => $terreno->id,
                'somente_atual' => true,
                'limit' => 5,
            ]))
        );

        $legalizacao = $this->toolArray(
            $legalizacaoTool->handle(new AiToolRequest([
                'terreno_id' => $terreno->id,
                'limit' => 3,
            ]))
        );

        $comite = $this->toolArray(
            $comiteTool->handle(new AiToolRequest([
                'terreno_id' => $terreno->id,
                'status' => '',
                'limit' => 3,
            ]))
        );

        $negociacao = $this->toolArray(
            $negociacaoTool->handle(new AiToolRequest([
                'terreno_id' => $terreno->id,
                'status' => '',
                'limit' => 3,
            ]))
        );

        $documentos = $this->toolArray(
            $documentosTool->handle(new AiToolRequest([
                'terreno_id' => $terreno->id,
                'limit' => 10,
            ]))
        );

        $tasks = $this->toolArray(
            $tasksTool->handle(new AiToolRequest([
                'terreno_id' => $terreno->id,
                'limit' => 10,
            ]))
        );

        $score = $this->toolArray(
            $scoreTool->handle(new AiToolRequest([
                'terreno_id' => $terreno->id,
            ]))
        );

        $ranking = $this->toolArray(
            $rankingTool->handle(new AiToolRequest([
                'limit' => 30,
            ]))
        );

        $predict = $this->toolArray(
            $predictTool->handle(new AiToolRequest([
                'terreno_id' => $terreno->id,
            ]))
        );

        $vgv = $this->toolArray(
            $vgvTool->handle(new AiToolRequest([
                'terreno_id' => $terreno->id,
            ]))
        );

        $dashboard = $this->toolArray($dashboardTool->handle(new AiToolRequest([])));

        $monitor = $this->toolArray(
            $monitorTool->handle(new AiToolRequest([
                'focus_area' => '',
                'limit' => 10,
            ]))
        );

        $anomalies = $this->toolArray(
            $anomaliesTool->handle(new AiToolRequest([
                'category' => '',
                'limit' => 10,
            ]))
        );

        $insights = $this->toolArray(
            $insightsTool->handle(new AiToolRequest([
                'limit' => 8,
            ]))
        );

        $trends = $this->toolArray(
            $trendsTool->handle(new AiToolRequest([
                'dimension' => '',
            ]))
        );

        $compareResponsavel = $this->toolArray(
            $compareTool->handle(new AiToolRequest([
                'dimension' => 'responsavel',
                'limit' => 10,
            ]))
        );

        $compareCidade = $this->toolArray(
            $compareTool->handle(new AiToolRequest([
                'dimension' => 'cidade',
                'limit' => 10,
            ]))
        );

        $polygon = $this->normalizePolygon($terreno->polygon_coords ?? []);
        $geoTopografia = $geo['topografia'] ?? [];
        $centroid = $geoTopografia['centroide'] ?? ($polygon !== [] ? $this->polygonCentroid($polygon) : null);
        $supportPoints = $this->collectSupportPoints($geo['pontos_de_apoio'] ?? []);
        $roads = $this->collectRoads($geo['vias'] ?? []);
        $mapDataUri = $this->buildPolygonMapDataUri($polygon, $centroid, $supportPoints);

        $currentViabilidade = $this->firstItem($viabilidades['items'] ?? []);
        $currentLegalizacao = $this->firstItem($legalizacao['items'] ?? []);
        $currentComite = $this->firstItem($comite['items'] ?? []);
        $currentNegociacao = $this->firstItem($negociacao['items'] ?? []);

        $cityProfile = null;
        if ($terreno->cidade_code && $terreno->estado) {
            $cityProfile = $this->toolArray(
                $cityTool->handle(new AiToolRequest([
                    'codigo_municipio' => $terreno->cidade_code,
                    'uf' => $terreno->estado,
                ]))
            );
        }

        $cityHighlights = $this->buildCityHighlights($cityProfile);
        $cadastroSummary = $this->buildCadastroSummary($terreno, $detail, $polygon);
        $geoSummary = $this->buildGeoSummary(
            $geo,
            $polygon,
            $centroid,
            $supportPoints,
            $roads,
            $mapDataUri
        );
        $workflowSummary = $this->buildWorkflowSummary($terreno);
        $viabilitySummary = $this->buildViabilitySummary($currentViabilidade, $viabilidades);
        $operationsSummary = $this->buildOperationsSummary(
            $currentLegalizacao,
            $currentComite,
            $currentNegociacao,
            $documentos,
            $tasks
        );
        $rankingPosition = $this->rankingPosition($ranking, $terreno->id);
        $marketSummary = $this->buildMarketSummary(
            $dashboard,
            $score,
            $rankingPosition,
            $predict,
            $vgv,
            $insights,
            $monitor,
            $anomalies,
            $trends,
            $compareResponsavel,
            $compareCidade
        );

        $aiNarrative = $this->generateNarrative([
            'terreno' => [
                'id' => $terreno->id,
                'nome' => $terreno->nome,
                'cidade' => $terreno->relationLoaded('cidade') && $terreno->cidade
                    ? $terreno->cidade->city
                    : ($terreno->cidade_code ?? '—'),
                'estado' => $terreno->estado,
                'area_calculada' => $terreno->area_calculada ? (float) $terreno->area_calculada : null,
                'valor' => $terreno->valor ? (float) $terreno->valor : null,
                'workflow_status_code' => $terreno->workflow_status_code,
                'workflow_status_label' => $terreno->workflow_status_label,
                'workflow_stage' => $terreno->workflow_stage,
            ],
            'cadastroSummary' => $cadastroSummary,
            'geoSummary' => $geoSummary,
            'cityHighlights' => $cityHighlights,
            'workflowSummary' => $workflowSummary,
            'viabilitySummary' => $viabilitySummary,
            'operationsSummary' => $operationsSummary,
            'marketSummary' => $marketSummary,
            'supportPoints' => $supportPoints,
            'roads' => $roads,
        ]);

        return [
            'title' => "Relatório SIG IA do Terreno #{$terreno->id}",
            'filename' => Str::slug("relatorio-sig-ia-terreno-{$terreno->id}-{$terreno->nome}"),
            'html_content' => $this->composeHtmlContent($aiNarrative['html'], $mapDataUri),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolArray(Stringable|string $result): array
    {
        $text = (string) $result;
        $decoded = json_decode($text, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return ['_text' => $text];
    }

    /**
     * @param  array<string, mixed>  $rankingResult
     */
    private function rankingPosition(array $rankingResult, int $terrenoId): ?int
    {
        $ranking = $rankingResult['ranking'] ?? null;
        if (! is_array($ranking)) {
            return null;
        }

        foreach (array_values($ranking) as $index => $item) {
            if (is_array($item) && (int) ($item['terreno_id'] ?? 0) === $terrenoId) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * @param  mixed  $polygon
     * @return array<int, array{lat: float, lng: float}>
     */
    private function normalizePolygon(mixed $polygon): array
    {
        if (! is_array($polygon)) {
            return [];
        }

        return array_values(array_filter(array_map(static function (mixed $point): ?array {
            if (! is_array($point)) {
                return null;
            }

            if (! array_key_exists('lat', $point) || ! array_key_exists('lng', $point)) {
                return null;
            }

            return [
                'lat' => (float) $point['lat'],
                'lng' => (float) $point['lng'],
            ];
        }, $polygon)));
    }

    /**
     * @param  array<string, mixed>  $amenities
     * @return array<int, array{categoria: string, nome: string, distancia_metros: int, lat: float, lng: float}>
     */
    private function collectSupportPoints(array $amenities): array
    {
        $points = [];

        foreach ($amenities as $categoria => $items) {
            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (! is_array($item) || ! array_key_exists('lat', $item) || ! array_key_exists('lng', $item)) {
                    continue;
                }

                $points[] = [
                    'categoria' => (string) $categoria,
                    'nome' => (string) ($item['name'] ?? 'Sem nome'),
                    'distancia_metros' => (int) ($item['distancia_metros'] ?? 0),
                    'lat' => (float) $item['lat'],
                    'lng' => (float) $item['lng'],
                ];
            }
        }

        usort($points, static fn (array $a, array $b): int => $a['distancia_metros'] <=> $b['distancia_metros']);

        return array_slice($points, 0, 12);
    }

    /**
     * @param  array<int, mixed>  $roads
     * @return array<int, array{name: string, type: string}>
     */
    private function collectRoads(array $roads): array
    {
        $items = [];

        foreach ($roads as $road) {
            if (is_string($road)) {
                $items[] = [
                    'name' => $road,
                    'type' => 'rua',
                ];

                continue;
            }

            if (! is_array($road)) {
                continue;
            }

            $name = (string) ($road['name'] ?? $road['long_name'] ?? 'Sem nome');
            $items[] = [
                'name' => $name,
                'type' => (string) ($road['type'] ?? 'rua'),
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return array_values(array_unique($items, SORT_REGULAR));
    }

    /**
     * @param  array<string, mixed>|null  $center
     * @param  array<int, array{categoria: string, nome: string, distancia_metros: int, lat: float, lng: float}>  $supportPoints
     */
    private function buildPolygonMapDataUri(array $polygon, ?array $center, array $supportPoints): string
    {
        if ($polygon === []) {
            return '';
        }

        $points = $polygon;
        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');

        $south = min(...$lats);
        $north = max(...$lats);
        $west = min(...$lngs);
        $east = max(...$lngs);

        $latSpan = max(0.000001, $north - $south);
        $lngSpan = max(0.000001, $east - $west);

        $paddingX = $lngSpan * 0.12;
        $paddingY = $latSpan * 0.12;

        $west -= $paddingX;
        $east += $paddingX;
        $south -= $paddingY;
        $north += $paddingY;

        $width = 1200;
        $height = 680;
        $pointToSvg = static function (float $lat, float $lng) use ($west, $east, $south, $north, $width, $height): array {
            $x = (($lng - $west) / max(0.000001, $east - $west)) * $width;
            $y = (($north - $lat) / max(0.000001, $north - $south)) * $height;

            return [round($x, 2), round($y, 2)];
        };

        $image = imagecreatetruecolor($width, $height);
        imageantialias($image, true);

        $backgroundTop = imagecolorallocate($image, 244, 246, 251) ?: 0;
        $backgroundBottom = imagecolorallocate($image, 238, 242, 248) ?: 0;
        $gridColor = imagecolorallocatealpha($image, 216, 224, 236, 55) ?: 0;
        $borderColor = imagecolorallocate($image, 46, 107, 255) ?: 0;
        $borderFill = imagecolorallocatealpha($image, 46, 107, 255, 96) ?: 0;
        $centerOuter = imagecolorallocatealpha($image, 46, 107, 255, 84) ?: 0;
        $centerInner = imagecolorallocate($image, 46, 107, 255) ?: 0;
        $supportBorder = imagecolorallocate($image, 255, 255, 255) ?: 0;
        $supportPalette = [
            'escola' => imagecolorallocate($image, 46, 107, 255) ?: 0,
            'universidade' => imagecolorallocate($image, 123, 97, 255) ?: 0,
            'hospital' => imagecolorallocate($image, 217, 57, 51) ?: 0,
            'clinica' => imagecolorallocate($image, 224, 164, 54) ?: 0,
            'farmacia' => imagecolorallocate($image, 30, 138, 91) ?: 0,
            'mercado' => imagecolorallocate($image, 30, 138, 91) ?: 0,
            'banco' => imagecolorallocate($image, 123, 97, 255) ?: 0,
            'posto_gasolina' => imagecolorallocate($image, 224, 164, 54) ?: 0,
        ];

        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / ($height - 1);
            $r = max(0, min(255, (int) round(244 + (238 - 244) * $ratio)));
            $g = max(0, min(255, (int) round(246 + (242 - 246) * $ratio)));
            $b = max(0, min(255, (int) round(251 + (248 - 251) * $ratio)));
            $rowColor = imagecolorallocate($image, $r, $g, $b) ?: 0;
            imageline($image, 0, $y, $width, $y, $rowColor);
        }

        foreach ([120, 240, 360, 480, 600, 720, 840, 960, 1080] as $x) {
            imageline($image, $x, 0, $x, $height, $gridColor);
        }

        foreach ([113, 226, 339, 452, 565] as $y) {
            imageline($image, 0, $y, $width, $y, $gridColor);
        }

        $flatPolygonPoints = [];
        foreach ($points as $point) {
            $svgPoint = $pointToSvg($point['lat'], $point['lng']);
            $flatPolygonPoints[] = (int) round($svgPoint[0]);
            $flatPolygonPoints[] = (int) round($svgPoint[1]);
        }

        if (count($flatPolygonPoints) >= 6) {
            imagefilledpolygon($image, $flatPolygonPoints, count($points), $borderFill);

            $firstX = $flatPolygonPoints[0];
            $firstY = $flatPolygonPoints[1];
            $previousX = $firstX;
            $previousY = $firstY;

            for ($i = 2; $i < count($flatPolygonPoints); $i += 2) {
                $currentX = $flatPolygonPoints[$i];
                $currentY = $flatPolygonPoints[$i + 1];
                imageline($image, $previousX, $previousY, $currentX, $currentY, $borderColor);
                $previousX = $currentX;
                $previousY = $currentY;
            }

            imageline($image, $previousX, $previousY, $firstX, $firstY, $borderColor);
        }

        foreach ($supportPoints as $index => $supportPoint) {
            $svgPoint = $pointToSvg($supportPoint['lat'], $supportPoint['lng']);
            $x = (int) round($svgPoint[0]);
            $y = (int) round($svgPoint[1]);
            $color = $supportPalette[$supportPoint['categoria']] ?? $borderColor;

            imagefilledellipse($image, $x, $y, 14, 14, $supportBorder);
            imagefilledellipse($image, $x, $y, 8, 8, $color);

            $label = (string) ($index + 1);
            imagestring($image, 2, $x + 10, $y - 7, $label, $borderColor);
        }

        if (is_array($center) && isset($center['lat'], $center['lng'])) {
            $svgCenter = $pointToSvg((float) $center['lat'], (float) $center['lng']);
            $x = (int) round($svgCenter[0]);
            $y = (int) round($svgCenter[1]);

            imagefilledellipse($image, $x, $y, 30, 30, $centerOuter);
            imagefilledellipse($image, $x, $y, 10, 10, $centerInner);
        }

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($binary);
    }

    /**
     * @param  array<int, array{categoria: string, nome: string, distancia_metros: int, lat: float, lng: float}>  $supportPoints
     * @return array<string, mixed>
     */
    private function buildGeoSummary(array $geo, array $polygon, ?array $center, array $supportPoints, array $roads, string $mapDataUri): array
    {
        $topografia = $geo['topografia'] ?? [];
        $area = $geo['area'] ?? [];
        $declividade = $geo['declividade'] ?? [];
        $app = $geo['app'] ?? [];

        return [
            'vertices' => count($polygon),
            'centroid' => $center,
            'bounding_box' => $topografia['bounding_box'] ?? null,
            'radius_metros' => $geo['radius_metros_usado'] ?? null,
            'area_total_m2' => $area['area_total_m2'] ?? null,
            'area_util_m2' => $area['area_util_m2'] ?? null,
            'area_app_m2' => $app['area_app_m2'] ?? null,
            'area_declividade_m2' => $area['area_declividade_m2'] ?? null,
            'percentual_aproveitamento' => $area['percentual_aproveitamento'] ?? null,
            'declividade' => [
                'classificacao' => $declividade['classificacao'] ?? null,
                'avaliacao' => $declividade['avaliacao'] ?? null,
                'impacto_custo' => $declividade['impacto_custo'] ?? null,
                'percentual_maximo' => $declividade['percentual_maximo'] ?? null,
                'percentual_medio' => $declividade['percentual_medio'] ?? null,
            ],
            'roads' => $roads,
            'support_points' => $supportPoints,
            'map_data_uri' => $mapDataUri,
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function buildCadastroSummary(Terreno $terreno, array $detail, array $polygon): array
    {
        $cityName = $terreno->relationLoaded('cidade') && $terreno->cidade
            ? $terreno->cidade->city
            : ($terreno->cidade_nome ?? $terreno->cidade_code ?? '—');

        $detailTotals = $detail['totais'] ?? [];

        return [
            ['label' => 'Nome', 'value' => $terreno->nome ?? '—'],
            ['label' => 'Cidade / UF', 'value' => trim($cityName.' / '.($terreno->estado ?? ''))],
            ['label' => 'Endereço', 'value' => $terreno->endereco ?? '—'],
            ['label' => 'Bairro', 'value' => $terreno->bairro ?? '—'],
            ['label' => 'CEP', 'value' => $terreno->cep ?? '—'],
            ['label' => 'Zona', 'value' => $terreno->zona ?? '—'],
            ['label' => 'Distrito', 'value' => $terreno->distrito ?? '—'],
            ['label' => 'Operação urbana', 'value' => $terreno->operacao_urbana ?? '—'],
            ['label' => 'Responsável', 'value' => $terreno->responsavel?->name ?? '—'],
            ['label' => 'Corretor externo', 'value' => $terreno->corretorExterno?->name ?? '—'],
            ['label' => 'Regional', 'value' => $terreno->regional?->nome ?? '—'],
            ['label' => 'Valor', 'value' => $this->formatCurrency((float) ($terreno->valor ?? 0))],
            ['label' => 'Área calculada', 'value' => $this->formatNumber($terreno->area_calculada ? (float) $terreno->area_calculada : null).' m²'],
            ['label' => 'Vértices do polígono', 'value' => (string) count($polygon)],
            ['label' => 'Apresentação', 'value' => $terreno->data_apresentacao?->format('d/m/Y') ?? '—'],
            ['label' => 'Negociação', 'value' => $terreno->data_negociacao?->format('d/m/Y') ?? '—'],
            ['label' => 'Opção', 'value' => $terreno->data_opcao?->format('d/m/Y') ?? '—'],
            ['label' => 'Contrato', 'value' => $terreno->data_contrato?->format('d/m/Y') ?? '—'],
            ['label' => 'Descarte', 'value' => $terreno->data_descarte?->format('d/m/Y') ?? '—'],
            ['label' => 'Observações', 'value' => $terreno->observacoes ?? '—'],
            ['label' => 'Documentos', 'value' => (string) ($detailTotals['documentos'] ?? '—')],
            ['label' => 'Contatos', 'value' => (string) ($detailTotals['contatos'] ?? '—')],
            ['label' => 'Proprietários', 'value' => (string) ($detailTotals['proprietarios'] ?? '—')],
            ['label' => 'Viabilidades', 'value' => (string) ($detailTotals['viabilidades'] ?? '—')],
            ['label' => 'Projetos', 'value' => (string) ($detailTotals['projetos'] ?? '—')],
            ['label' => 'Atualizado em', 'value' => $terreno->updated_at?->format('d/m/Y H:i') ?? '—'],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function buildWorkflowSummary(Terreno $terreno): array
    {
        return [
            ['label' => 'Etapa', 'value' => $terreno->workflow_stage ?? '—'],
            ['label' => 'Status', 'value' => $terreno->workflow_status_label ?? ($terreno->workflow_status_code ?? '—')],
            ['label' => 'Motivo', 'value' => $terreno->workflow_reason_code ?? '—'],
            ['label' => 'Notas', 'value' => $terreno->workflow_reason_notes ?? '—'],
            ['label' => 'Última mudança', 'value' => $terreno->workflow_status_changed_at?->format('d/m/Y H:i') ?? '—'],
            ['label' => 'Qualificação concluída', 'value' => $terreno->qualification_completed_at?->format('d/m/Y H:i') ?? '—'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $viability
     * @param  array<string, mixed>  $viabilidades
     * @return array<string, mixed>
     */
    private function buildViabilitySummary(?array $viability, array $viabilidades): array
    {
        $items = $viabilidades['items'] ?? [];
        $latest = is_array($viability) ? $viability : ($this->firstItem($items) ?? []);

        return [
            'current' => $latest,
            'history' => array_slice(is_array($items) ? $items : [], 0, 3),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOperationsSummary(
        ?array $legalizacao,
        ?array $comite,
        ?array $negociacao,
        array $documentos,
        array $tasks
    ): array {
        return [
            'legalizacao' => $legalizacao,
            'comite' => $comite,
            'negociacao' => $negociacao,
            'documentos' => [
                'total' => $documentos['total'] ?? 0,
                'resumo' => $documentos['resumo'] ?? [],
                'items' => array_slice($documentos['items'] ?? [], 0, 5),
            ],
            'tasks' => [
                'total' => $tasks['total'] ?? 0,
                'resumo' => $tasks['resumo'] ?? [],
                'items' => array_slice($tasks['items'] ?? [], 0, 5),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMarketSummary(
        array $dashboard,
        array $score,
        ?int $rankingPosition,
        array $predict,
        array $vgv,
        array $insights,
        array $monitor,
        array $anomalies,
        array $trends,
        array $compareResponsavel,
        array $compareCidade
    ): array {
        return [
            'dashboard' => $dashboard,
            'dashboardTerrenos' => $dashboard['terrenos'] ?? [],
            'topCidades' => $dashboard['top_cidades'] ?? [],
            'score' => $score,
            'rankingPosition' => $rankingPosition,
            'predict' => $predict,
            'vgv' => $vgv,
            'insights' => $insights,
            'monitor' => $monitor,
            'anomalies' => $anomalies,
            'trends' => $trends,
            'compareResponsavel' => $compareResponsavel,
            'compareCidade' => $compareCidade,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstItem(array $items): ?array
    {
        $first = $items[0] ?? null;

        return is_array($first) ? $first : null;
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     * @return array{lat: float, lng: float}|null
     */
    private function polygonCentroid(array $polygon): ?array
    {
        $count = count($polygon);

        if ($count === 0) {
            return null;
        }

        return [
            'lat' => array_sum(array_column($polygon, 'lat')) / $count,
            'lng' => array_sum(array_column($polygon, 'lng')) / $count,
        ];
    }

    private function formatCurrency(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return 'R$ '.number_format($value, 2, ',', '.');
    }

    private function formatNumber(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '—';
        }

        return number_format((float) $value, 2, ',', '.');
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function buildCityHighlights(?array $cityProfile): array
    {
        if (! is_array($cityProfile)) {
            return [];
        }

        return [
            ['label' => 'Município', 'value' => (string) data_get($cityProfile, 'municipio.nome', '—')],
            ['label' => 'UF', 'value' => (string) data_get($cityProfile, 'municipio.uf', '—')],
            ['label' => 'Região', 'value' => (string) data_get($cityProfile, 'municipio.regiao', '—')],
            ['label' => 'PIB total', 'value' => (string) data_get($cityProfile, 'panorama.destaques.pib_total.valor', '—')],
            ['label' => 'PIB per capita', 'value' => (string) data_get($cityProfile, 'panorama.destaques.pib_per_capita.valor', '—')],
            ['label' => 'População estimada', 'value' => (string) data_get($cityProfile, 'panorama.destaques.populacao_estimada.valor', '—')],
            ['label' => 'Renda per capita', 'value' => (string) data_get($cityProfile, 'trabalho_e_renda.renda_per_capita_domiciliar.valor', '—')],
            ['label' => 'Domicílios com internet', 'value' => (string) data_get($cityProfile, 'habitacao.domicilios_com_internet.valor', '—')],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{markdown: string, html: string}
     */
    private function generateNarrative(array $context): array
    {
        $jsonContext = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            ?: '{}';

        $prompt = <<<PROMPT
Você está escrevendo um relatório executivo do SIG IA para um terreno específico.

Regras obrigatórias:
- Escreva exclusivamente em português brasileiro.
- Baseie-se apenas no CONTEXTO fornecido.
- Não invente dados, não especule e não faça perguntas.
- Não use HTML.
- Produza o corpo completo do relatório, como se fosse a resposta final do chat da SIG IA.
- Escreva com tom executivo, objetivo e analítico, sem texto de preenchimento.
- Use todos os dados disponíveis no contexto para montar uma leitura completa do terreno.
- Use exatamente esta estrutura e ordem:

**Resumo Executivo**
2 a 5 linhas curtas e impactantes. Explique o panorama geral do terreno.

---

**Cadastro e Localização**
- Nome, cidade, UF, endereço, bairro, zona, distrito e responsável
- Datas e observações relevantes do cadastro

---

**Geografia, Mapa e Entorno**
- Área, polígono, centroide, declividade, APP e leitura do entorno
- Pontos de apoio, vias próximas e qualquer restrição geográfica relevante

---

**Cidade e Contexto IBGE**
- Informações municipais relevantes que ajudem a contextualizar o terreno

---

**Workflow e Operação**
- Status atual, etapa, motivo, legalização, comitê e negociação
- Inclua pendências, atrasos e sinais de avanço

---

**Viabilidade**
- Situação atual, histórico resumido e leitura executiva da aprovação

---

**Documentos e Tarefas**
- Documentos, tarefas e pendências mais importantes

---

**Mercado, Score e Comparativos**
- Score, ranking, probabilidade de aprovação, VGV, insights, tendências e comparações

---

**Riscos e Pontos de Atenção**
- Liste os maiores riscos primeiro
- Destaque inconsistências, atrasos, lacunas de dados e sinais de alerta

---

**Recomendações Práticas**
1. Ação mais urgente
2. Próxima ação
3. Ação complementar

---

**Próximos Passos Sugeridos**
- Bullets acionáveis e claros
- Inclua orientações que ajudem a tomada de decisão

Contexto estruturado do terreno:
{$jsonContext}

Se algum dado não estiver presente, diga explicitamente "Não informado".
PROMPT;

        $agentRoute = app(AiProviderRouter::class)->getAgentWithFallback();
        $agent = $agentRoute['agent'];

        try {
            $response = $agent->prompt(
                $prompt,
                provider: $agentRoute['provider'],
                model: $agentRoute['model'],
                timeout: 180
            );

            $markdown = trim((string) $response);

            if ($markdown === '') {
                throw new RuntimeException('A IA não retornou conteúdo para o relatório.');
            }
        } catch (\Throwable $e) {
            $markdown = $this->fallbackNarrative($context, $e->getMessage());
        }

        $converter = new GithubFlavoredMarkdownConverter;
        $html = $converter->convert($markdown)->getContent();

        return [
            'markdown' => $markdown,
            'html' => $html,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function fallbackNarrative(array $context, string $reason): string
    {
        $terreno = $context['terreno'] ?? [];
        $workflow = $context['workflowSummary'] ?? [];
        $geo = $context['geoSummary'] ?? [];
        $market = $context['marketSummary'] ?? [];

        $nome = (string) ($terreno['nome'] ?? '—');
        $cidade = (string) ($terreno['cidade'] ?? '—');
        $estado = (string) ($terreno['estado'] ?? '—');
        $status = $this->findSummaryValue($workflow, 'Status') ?? 'Não informado';
        $score = data_get($market, 'score.score', '—');
        $tier = data_get($market, 'score.tier', '—');
        $area = data_get($geo, 'area_util_m2', '—');
        $support = count((array) ($context['supportPoints'] ?? []));

        return <<<MD
**Resumo Executivo**
A IA não conseguiu redigir a narrativa completa neste momento, então este relatório
foi gerado com base nos dados estruturados já consultados. Terreno
**{$nome}** em **{$cidade} / {$estado}**.
Status atual **{$status}**. Score **{$score}** ({$tier}).

---

**Principais Evidências**
- Área útil: **{$area} m²**
- Pontos de apoio próximos: **{$support}**
- Razão técnica: {$reason}

---

**Riscos e Pontos de Atenção**
- A narrativa automática da IA não foi concluída
- Verifique os dados de origem antes de compartilhar externamente

---

**Recomendações Práticas**
1. Reexecutar a geração da narrativa assim que a IA estiver disponível
2. Revisar os dados cadastrais e geográficos do terreno
3. Manter o PDF com os anexos técnicos gerados

---

**Próximos Passos Sugeridos**
- Reprocessar o relatório com a SIG IA
- Conferir a consistência dos dados do terreno
- Validar o mapa e os anexos técnicos
MD;
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $summary
     */
    private function findSummaryValue(array $summary, string $label): ?string
    {
        foreach ($summary as $item) {
            if ($item['label'] === $label) {
                return $item['value'];
            }
        }

        return null;
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
