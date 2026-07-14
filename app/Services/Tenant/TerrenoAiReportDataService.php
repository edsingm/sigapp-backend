<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Terreno;
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
use Laravel\Ai\Tools\Request as AiToolRequest;
use Stringable;

final class TerrenoAiReportDataService
{
    public function __construct(
        private readonly TerrenoAiReportMapRenderer $mapRenderer,
    ) {}

    /**
     * @return array{
     *   context: array<string, mixed>,
     *   map_data_uri: string
     * }
     */
    public function collect(Terreno $terreno): array
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

        $map = $this->mapRenderer->prepare(
            $terreno->polygon_coords ?? [],
            $geo
        );
        $polygon = $map['polygon'];
        $centroid = $map['centroid'];
        $supportPoints = $map['support_points'];
        $roads = $map['roads'];
        $mapDataUri = $map['map_data_uri'];

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

        $context = [
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
        ];

        return [
            'context' => $context,
            'map_data_uri' => $mapDataUri,
        ];
    }

    /** @return array<string, mixed> */
    private function toolArray(Stringable|string $result): array
    {
        $text = (string) $result;
        $decoded = json_decode($text, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return ['_text' => $text];
    }

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

    /** @return array<string, mixed> */
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

    /** @return array<int, array{label: string, value: string}> */
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

    /** @return array<int, array{label: string, value: string}> */
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

    /** @return array<string, mixed> */
    private function buildViabilitySummary(?array $viability, array $viabilidades): array
    {
        $items = $viabilidades['items'] ?? [];
        $latest = is_array($viability) ? $viability : ($this->firstItem($items) ?? []);

        return [
            'current' => $latest,
            'history' => array_slice(is_array($items) ? $items : [], 0, 3),
        ];
    }

    /** @return array<string, mixed> */
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

    /** @return array<string, mixed> */
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

    /** @return array<string, mixed>|null */
    private function firstItem(array $items): ?array
    {
        $first = $items[0] ?? null;

        return is_array($first) ? $first : null;
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

    /** @return array<int, array{label: string, value: string}> */
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
}
