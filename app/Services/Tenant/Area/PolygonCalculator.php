<?php

namespace App\Services\Tenant\Area;

/**
 * Cálculos geoespaciais em polígonos.
 *
 * Implementa a Shoelace formula para cálculo de área,
 * fórmula de Haversine para distâncias, e operações
 * básicas de interseção e buffer.
 */
class PolygonCalculator
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    /**
     * Calcula a área de um polígono em metros quadrados usando a Shoelace formula.
     *
     * O polígono deve ser um array de pontos [{lat, lng}, ...].
     * A projeção usa fatores de conversão grau→metro que variam com a latitude
     * (1° lat ≈ 110 540 m; 1° lng ≈ 111 320 · cos(lat) m).
     *
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     */
    public function calculateArea(array $polygon): float
    {
        $n = count($polygon);

        if ($n < 3) {
            return 0.0;
        }

        // Latitude média para ajustar a escala de longitude
        $meanLat = array_sum(array_column($polygon, 'lat')) / $n;
        $cosLat = cos(deg2rad($meanLat));

        $latFactor = 110_540.0;               // metros por grau de latitude
        $lngFactor = 111_320.0 * $cosLat;     // metros por grau de longitude

        $area = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;

            $x1 = $polygon[$i]['lng'] * $lngFactor;
            $y1 = $polygon[$i]['lat'] * $latFactor;
            $x2 = $polygon[$j]['lng'] * $lngFactor;
            $y2 = $polygon[$j]['lat'] * $latFactor;

            $area += ($x1 * $y2) - ($x2 * $y1);
        }

        return abs($area) / 2.0;
    }

    /**
     * Calcula a distância em metros entre dois pontos geográficos (Haversine).
     *
     * @param  array{lat: float, lng: float}  $p1
     * @param  array{lat: float, lng: float}  $p2
     */
    public function haversineDistance(array $p1, array $p2): float
    {
        $lat1 = deg2rad($p1['lat']);
        $lat2 = deg2rad($p2['lat']);
        $dLat = deg2rad($p2['lat'] - $p1['lat']);
        $dLng = deg2rad($p2['lng'] - $p1['lng']);

        $a = sin($dLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * Calcula a declividade percentual entre dois pontos dado o delta de elevação.
     *
     * @param  array{lat: float, lng: float}  $p1
     * @param  array{lat: float, lng: float}  $p2
     * @param  float  $elevationDiff  diferença de elevação em metros (pode ser negativa)
     */
    public function slopePercent(array $p1, array $p2, float $elevationDiff): float
    {
        $horizontalDist = $this->haversineDistance($p1, $p2);

        if ($horizontalDist <= 0.0) {
            return 0.0;
        }

        return abs($elevationDiff / $horizontalDist) * 100.0;
    }

    /**
     * Retorna o bounding box de um polígono.
     *
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     * @return array{south: float, north: float, west: float, east: float}
     */
    public function boundingBox(array $polygon): array
    {
        $lats = array_column($polygon, 'lat');
        $lngs = array_column($polygon, 'lng');

        if (empty($lats) || empty($lngs)) {
            return ['south' => 0.0, 'north' => 0.0, 'west' => 0.0, 'east' => 0.0];
        }

        return [
            'south' => min(...$lats),
            'north' => max(...$lats),
            'west' => min(...$lngs),
            'east' => max(...$lngs),
        ];
    }

    /**
     * Cria um buffer retangular simplificado ao redor de um ponto (em metros).
     *
     * Útil para criar bounding-boxes de consulta a APIs externas.
     *
     * @param  array{lat: float, lng: float}  $center
     * @return array{south: float, north: float, west: float, east: float}
     */
    public function bufferBoundingBox(array $center, float $radiusMeters): array
    {
        $cosLat = cos(deg2rad($center['lat']));

        $deltaLat = $radiusMeters / 110_540.0;
        $deltaLng = $radiusMeters / (111_320.0 * max($cosLat, 0.01));

        return [
            'south' => $center['lat'] - $deltaLat,
            'north' => $center['lat'] + $deltaLat,
            'west' => $center['lng'] - $deltaLng,
            'east' => $center['lng'] + $deltaLng,
        ];
    }

    /**
     * Centroide de um polígono (média dos vértices).
     *
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     * @return array{lat: float, lng: float}
     */
    public function centroid(array $polygon): array
    {
        $n = count($polygon);

        return [
            'lat' => array_sum(array_column($polygon, 'lat')) / $n,
            'lng' => array_sum(array_column($polygon, 'lng')) / $n,
        ];
    }
}
