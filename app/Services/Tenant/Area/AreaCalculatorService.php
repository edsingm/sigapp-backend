<?php

namespace App\Services\Tenant\Area;

use App\Enums\DeclividadeClassificacao;
use App\Models\Tenant\Terreno;
use Illuminate\Support\Facades\Log;

class AreaCalculatorService
{
    public function __construct(
        private readonly PolygonCalculator $polygonCalc,
        private readonly TopographyService $topography,
        private readonly HydrographyService $hydrography,
    ) {}

    /**
     * @return array{
     *     area_total: float,
     *     area_declividade: float,
     *     area_app: float,
     *     area_util: float,
     *     percentual_aproveitamento: float,
     *     declividade_classificacao: string|null,
     *     declividade_avaliacao: string|null,
     *     declividade_impacto_custo: string|null,
     *     declividade_percentual_maximo: float|null,
     *     declividade_percentual_medio: float|null,
     *     app_polygons: list<array{type: 'Polygon', coordinates: list<list<list<float>>>}>,
     *     steep_polygons: list<array{type: 'Polygon', coordinates: list<list<list<float>>>}>,
     * }
     */
    public function calculate(Terreno $terreno): array
    {
        $polygon = $terreno->polygon_coords;

        if (empty($polygon) || count($polygon) < 3) {
            return $this->emptyResult();
        }

        $areaTotal = $this->polygonCalc->calculateArea($polygon);

        if ($areaTotal <= 0.0) {
            return $this->emptyResult();
        }

        $areaDeclividade = $this->calculateSlopeArea($polygon);
        $areaAppResult = $this->hydrography->calculateAppArea($polygon);
        $areaUtil = max(0.0, $areaTotal - $areaDeclividade['area'] - $areaAppResult['area']);
        $percentual = ($areaUtil / $areaTotal) * 100.0;

        return [
            'area_total' => round($areaTotal, 2),
            'area_declividade' => round($areaDeclividade['area'], 2),
            'area_app' => round($areaAppResult['area'], 2),
            'area_util' => round($areaUtil, 2),
            'percentual_aproveitamento' => round($percentual, 2),
            'declividade_classificacao' => $areaDeclividade['classificacao']?->value,
            'declividade_avaliacao' => $areaDeclividade['classificacao']?->avaliacao(),
            'declividade_impacto_custo' => $areaDeclividade['classificacao']?->impactoNoCusto(),
            'declividade_percentual_maximo' => $areaDeclividade['percentual_maximo'],
            'declividade_percentual_medio' => $areaDeclividade['percentual_medio'],
            'app_polygons' => $areaAppResult['polygons'],
            'steep_polygons' => $areaDeclividade['polygons'],
        ];
    }

    /**
     * @return array{
     *     area: float,
     *     percentual_maximo: float|null,
     *     percentual_medio: float|null,
     *     classificacao: DeclividadeClassificacao|null,
     *     polygons: list<array{type: 'Polygon', coordinates: list<list<list<float>>>}>,
     * }
     */
    private function calculateSlopeArea(array $polygon): array
    {
        try {
            $elevations = $this->topography->getElevationsForPolygon($polygon);

            if (empty($elevations)) {
                return $this->emptySlopeResult();
            }

            $slopes = $this->topography->calculateSlopes($elevations);

            if (empty($slopes)) {
                return $this->emptySlopeResult();
            }

            $slopeValues = array_column($slopes, 'slope');
            $percentualMaximo = max($slopeValues);
            $percentualMedio = array_sum($slopeValues) / count($slopeValues);

            $classificacao = DeclividadeClassificacao::fromSlope($percentualMaximo);

            $steepPoints = array_values(array_filter(
                $slopes,
                fn (array $s) => $s['slope'] > 10.0,
            ));

            // Gerar polígonos para arestas com declividade > 30% (limite legal)
            $steepThreshold = 30.0;
            $steepPolygons = [];
            $n = count($polygon);

            foreach ($slopes as $i => $slope) {
                $bufferPolygon = match (true) {
                    $i < $n && $slope['slope'] > $steepThreshold => $this->createEdgeBuffer(
                        $polygon[$i],
                        $polygon[($i + 1) % $n],
                        3.0,
                    ),
                    default => [],
                };

                if (! empty($bufferPolygon)) {
                    $steepPolygons[] = [
                        'type' => 'Polygon',
                        'coordinates' => [$bufferPolygon],
                    ];
                }
            }

            if (empty($steepPoints)) {
                return [
                    'area' => 0.0,
                    'percentual_maximo' => round($percentualMaximo, 2),
                    'percentual_medio' => round($percentualMedio, 2),
                    'classificacao' => $classificacao,
                    'polygons' => $steepPolygons,
                ];
            }

            $lats = array_column($steepPoints, 'lat');
            $lngs = array_column($steepPoints, 'lng');

            $meanLat = array_sum($lats) / count($lats);
            $latFactor = 110_540.0;
            $lngFactor = 111_320.0 * cos(deg2rad($meanLat));

            $width = (max($lngs) - min($lngs)) * $lngFactor;
            $height = (max($lats) - min($lats)) * $latFactor;

            $steepRatio = count($steepPoints) / count($slopes);
            $totalArea = $this->polygonCalc->calculateArea($polygon);

            return [
                'area' => min($totalArea, $steepRatio * $totalArea),
                'percentual_maximo' => round($percentualMaximo, 2),
                'percentual_medio' => round($percentualMedio, 2),
                'classificacao' => $classificacao,
                'polygons' => $steepPolygons,
            ];

        } catch (\Throwable $e) {
            Log::warning('Erro ao calcular declividade, retornando 0', [
                'error' => $e->getMessage(),
            ]);

            return $this->emptySlopeResult();
        }
    }

    /**
     * Cria um retângulo ao longo de uma aresta com largura em metros para visualização.
     *
     * Usa projeção plana simplificada (latFactor/lngFactor) para calcular
     * o vetor perpendicular à aresta em coordenadas de projeção (metros).
     * Retorna coordenadas no padrão GeoJSON: [longitude, latitude].
     *
     * @param  array{lat: float, lng: float}  $from
     * @param  array{lat: float, lng: float}  $to
     * @return list<list<float>>
     */
    private function createEdgeBuffer(array $from, array $to, float $widthMeters): array
    {
        $meanLat = ($from['lat'] + $to['lat']) / 2;
        $cosLat = cos(deg2rad($meanLat));
        $latFactor = 110_540.0;
        $lngFactor = 111_320.0 * max($cosLat, 0.01);

        // Converter para coordenadas projetadas (metros)
        $fx = $from['lng'] * $lngFactor;
        $fy = $from['lat'] * $latFactor;
        $tx = $to['lng'] * $lngFactor;
        $ty = $to['lat'] * $latFactor;

        // Vetor da aresta
        $ex = $tx - $fx;
        $ey = $ty - $fy;
        $len = sqrt($ex * $ex + $ey * $ey);

        if ($len < 1.0) {
            return [];
        }

        // Vetor perpendicular unitário
        $px = -$ey / $len;
        $py = $ex / $len;

        $hw = $widthMeters / 2;

        // 4 cantos em coordenadas projetadas e converter de volta para lat/lng
        // GeoJSON: [longitude, latitude] pairs, closed ring
        return [
            [($fx - $px * $hw) / $lngFactor, ($fy - $py * $hw) / $latFactor],
            [($fx + $px * $hw) / $lngFactor, ($fy + $py * $hw) / $latFactor],
            [($tx + $px * $hw) / $lngFactor, ($ty + $py * $hw) / $latFactor],
            [($tx - $px * $hw) / $lngFactor, ($ty - $py * $hw) / $latFactor],
            [($fx - $px * $hw) / $lngFactor, ($fy - $py * $hw) / $latFactor],
        ];
    }

    /**
     * @return array{area: float, percentual_maximo: null, percentual_medio: null, classificacao: null, polygons: list<never>}
     */
    private function emptySlopeResult(): array
    {
        return [
            'area' => 0.0,
            'percentual_maximo' => null,
            'percentual_medio' => null,
            'classificacao' => null,
            'polygons' => [],
        ];
    }

    /**
     * @return array{
     *     area_total: float,
     *     area_declividade: float,
     *     area_app: float,
     *     area_util: float,
     *     percentual_aproveitamento: float,
     *     declividade_classificacao: null,
     *     declividade_avaliacao: null,
     *     declividade_impacto_custo: null,
     *     declividade_percentual_maximo: null,
     *     declividade_percentual_medio: null,
     *     app_polygons: list<never>,
     *     steep_polygons: list<never>,
     * }
     */
    private function emptyResult(): array
    {
        return [
            'area_total' => 0.0,
            'area_declividade' => 0.0,
            'area_app' => 0.0,
            'area_util' => 0.0,
            'percentual_aproveitamento' => 0.0,
            'declividade_classificacao' => null,
            'declividade_avaliacao' => null,
            'declividade_impacto_custo' => null,
            'declividade_percentual_maximo' => null,
            'declividade_percentual_medio' => null,
            'app_polygons' => [],
            'steep_polygons' => [],
        ];
    }
}
