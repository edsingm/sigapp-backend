<?php

declare(strict_types=1);

namespace App\Services\Tenant;

final class TerrenoAiReportMapRenderer
{
    /**
     * @return array{
     *   polygon: array<int, array{lat: float, lng: float}>,
     *   centroid: array<string, mixed>|null,
     *   support_points: array<int, array{categoria: string, nome: string, distancia_metros: int, lat: float, lng: float}>,
     *   roads: array<int, array{name: string, type: string}>,
     *   map_data_uri: string
     * }
     */
    public function prepare(mixed $polygonCoordinates, array $geo): array
    {
        $polygon = $this->normalizePolygon($polygonCoordinates);
        $geoTopografia = $geo['topografia'] ?? [];
        $centroid = is_array($geoTopografia)
            ? ($geoTopografia['centroide'] ?? ($polygon !== [] ? $this->polygonCentroid($polygon) : null))
            : ($polygon !== [] ? $this->polygonCentroid($polygon) : null);
        $supportPoints = $this->collectSupportPoints($geo['pontos_de_apoio'] ?? []);
        $roads = $this->collectRoads($geo['vias'] ?? []);

        return [
            'polygon' => $polygon,
            'centroid' => is_array($centroid) ? $centroid : null,
            'support_points' => $supportPoints,
            'roads' => $roads,
            'map_data_uri' => $this->buildPolygonMapDataUri($polygon, is_array($centroid) ? $centroid : null, $supportPoints),
        ];
    }

    /** @return array<int, array{lat: float, lng: float}> */
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

    /** @return array<int, array{categoria: string, nome: string, distancia_metros: int, lat: float, lng: float}> */
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

    /** @return array<int, array{name: string, type: string}> */
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

    /** @return array{lat: float, lng: float}|null */
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
}
