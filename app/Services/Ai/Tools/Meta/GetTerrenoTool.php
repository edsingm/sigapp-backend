<?php

namespace App\Services\Ai\Tools\Meta;

use App\Services\Ai\Tools\AiScoringService;
use App\Services\Ai\Tools\EstimateVgvTool;
use App\Services\Ai\Tools\GetTerrenoDetailsTool;
use App\Services\Ai\Tools\GetTerrenoGeoAnalysisTool;
use App\Services\Ai\Tools\GetTerrenoScoreTool;
use App\Services\Ai\Tools\PredictViabilityTool;
use App\Services\Tenant\Area\PolygonCalculator;
use App\Services\Tenant\Geo\GeoProximityService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Meta-tool: detalhe, geo, score e estimativas de um terreno.
 */
class GetTerrenoTool implements Tool
{
    use MetaToolSupport;

    public function description(): Stringable|string
    {
        return 'Terreno unificado. action=details|geo|score|predict_viability|estimate_vgv. '
            .'Requer terreno_id. details aceita mode=summary|full e flags include_*. '
            .'Não use para processo (viabilidade/comitê/legalização/negociação → GetTerrenoProcess).';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $this->action($request, 'details');
        $forward = $this->forwardRequest($request);

        return match ($action) {
            'details' => $this->call(new GetTerrenoDetailsTool, $forward),
            'geo' => $this->call(
                new GetTerrenoGeoAnalysisTool(
                    app(GeoProximityService::class),
                    app(PolygonCalculator::class)
                ),
                $forward
            ),
            'score' => $this->call(new GetTerrenoScoreTool(app(AiScoringService::class)), $forward),
            'predict_viability' => $this->call(app(PredictViabilityTool::class), $forward),
            'estimate_vgv' => $this->call(app(EstimateVgvTool::class), $forward),
            default => $this->unknownAction($action, [
                'details',
                'geo',
                'score',
                'predict_viability',
                'estimate_vgv',
            ]),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->required()
                ->description('details | geo | score | predict_viability | estimate_vgv')
                ->enum(['details', 'geo', 'score', 'predict_viability', 'estimate_vgv']),
            'terreno_id' => $schema->integer()
                ->required()
                ->description('ID do terreno.'),
            'mode' => $schema->string()
                ->description('details: summary|full')
                ->enum(['summary', 'full']),
            'include_viabilidades' => $schema->boolean()->description('details'),
            'include_negociacao' => $schema->boolean()->description('details'),
            'include_contrato' => $schema->boolean()->description('details'),
            'include_projetos' => $schema->boolean()->description('details'),
            'radius_metros' => $schema->integer()
                ->description('geo: raio de vias/pontos (padrão 1000)')
                ->min(100),
        ];
    }
}
