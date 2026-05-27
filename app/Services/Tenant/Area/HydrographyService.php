<?php

namespace App\Services\Tenant\Area;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consulta dados de hidrografia (rios, nascentes, lagos) para cálculo de APP.
 *
 * Estratégia MVP: Overpass API (OpenStreetMap) com cache de 30 dias.
 * Dados abertos, sem custo, cobertura razoável no Brasil.
 *
 * Futuro: migrar para shapefiles da ANA ou API WFS quando disponível.
 */
class HydrographyService
{
    /** Distância de APP em metros por tipo de corpo d'água (Código Florestal) */
    private const APP_BUFFER = [
        'waterway_river' => 50.0,
        'waterway_stream' => 30.0,
        'waterway_canal' => 15.0,
        'natural_spring' => 15.0,
        'natural_water' => 30.0,
        'waterway_ditch' => 5.0,
        'default' => 30.0,
    ];

    private const OVERPASS_API = 'https://overpass-api.de/api/interpreter';

    private const CACHE_TTL_DAYS = 30;

    public function __construct(
        private readonly PolygonCalculator $polygonCalc,
    ) {}

    /**
     * Calcula a área total de APP (em m²) e gera polígonos de buffer para visualização.
     *
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     * @return array{area: float, polygons: list<array{type: 'Polygon', coordinates: list<list<list<float>>>}>}
     */
    public function calculateAppArea(array $polygon): array
    {
        $waterBodies = $this->getWaterBodiesForPolygon($polygon);

        if (empty($waterBodies)) {
            return ['area' => 0.0, 'polygons' => []];
        }

        $totalAppArea = 0.0;
        $appPolygons = [];

        foreach ($waterBodies as $wb) {
            $bufferMeters = self::APP_BUFFER[$wb['type']] ?? self::APP_BUFFER['default'];

            $coords = $this->extractCoordinates($wb['geometry']);

            // Amostrar pontos (a cada 3 para reduzir processamento)
            $sampledCoords = array_filter(
                $coords,
                fn (int $key) => $key % 3 === 0,
                ARRAY_FILTER_USE_KEY,
            );

            foreach ($sampledCoords as $point) {
                if ($this->pointInPolygon($point, $polygon)) {
                    $totalAppArea += (2 * $bufferMeters) ** 2;

                    $polygonCoords = $this->makeBufferPolygon($point, $bufferMeters);
                    if (! empty($polygonCoords)) {
                        $appPolygons[] = [
                            'type' => 'Polygon',
                            'coordinates' => [$polygonCoords],
                        ];
                    }
                }
            }
        }

        // Limitar ao máximo da área do terreno
        $terrenoArea = $this->polygonCalc->calculateArea($polygon);

        return [
            'area' => min($totalAppArea, $terrenoArea),
            'polygons' => $appPolygons,
        ];
    }

    /**
     * Cria um polígono retangular de buffer ao redor de um ponto.
     *
     * Retorna coordenadas no padrão GeoJSON: [longitude, latitude].
     *
     * @param  array{lat: float, lng: float}  $center
     * @return list<list<float>>
     */
    private function makeBufferPolygon(array $center, float $radiusMeters): array
    {
        $bbox = $this->polygonCalc->bufferBoundingBox($center, $radiusMeters);

        // GeoJSON: [longitude, latitude] pairs, closed ring (SW→SE→NE→NW→SW)
        return [
            [$bbox['west'], $bbox['south']],
            [$bbox['east'], $bbox['south']],
            [$bbox['east'], $bbox['north']],
            [$bbox['west'], $bbox['north']],
            [$bbox['west'], $bbox['south']],
        ];
    }

    /**
     * Busca corpos d'água via Overpass API (OpenStreetMap) com cache.
     *
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     * @return array<int, array{type: string, geometry: array<string, mixed>}>
     */
    public function getWaterBodiesForPolygon(array $polygon): array
    {
        $bbox = $this->polygonCalc->boundingBox($polygon);
        $expanded = $this->expandBbox($bbox, 200.0);

        // Cache por bbox arredondado (2 casas decimais ≈ 1km)
        // Prefixo v2 para invalidar caches prévios que podem ter dados vazios (406)
        $cacheKey = sprintf(
            'hydrography:v2:%.2f:%.2f:%.2f:%.2f',
            $expanded['south'],
            $expanded['north'],
            $expanded['west'],
            $expanded['east'],
        );

        return Cache::tags(['hydrography'])->remember(
            $cacheKey,
            now()->addDays(self::CACHE_TTL_DAYS),
            fn () => $this->fetchFromOverpass($expanded),
        );
    }

    /**
     * Consulta a Overpass API para buscar hidrografia em um bounding box.
     *
     * @param  array{south: float, north: float, west: float, east: float}  $bbox
     * @return array<int, array{type: string, geometry: array<string, mixed>}>
     */
    private function fetchFromOverpass(array $bbox): array
    {
        $south = $bbox['south'];
        $north = $bbox['north'];
        $west = $bbox['west'];
        $east = $bbox['east'];

        // Query Overpass: rios, córregos, nascentes, lagos
        $query = <<<OVERPASS
        [out:json][timeout:30];
        (
          way["waterway"~"river|stream|canal|ditch"]({$south},{$west},{$north},{$east});
          relation["waterway"~"river|stream"]({$south},{$west},{$north},{$east});
          node["natural"="spring"]({$south},{$west},{$north},{$east});
          way["natural"="water"]({$south},{$west},{$north},{$east});
          relation["natural"="water"]({$south},{$west},{$north},{$east});
        );
        out body;
        >;
        out skel qt;
        OVERPASS;

        try {
            $response = Http::timeout(60)
                ->withUserAgent('SIGAPP/1.0')
                ->asForm()
                ->post(self::OVERPASS_API, [
                    'data' => $query,
                ]);

            if ($response->failed()) {
                Log::warning('Overpass API falhou', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return [];
            }

            $data = $response->json();
            $elements = $data['elements'] ?? [];

            return $this->buildFeatures($elements);

        } catch (\Throwable $e) {
            Log::error('Erro ao consultar Overpass API', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Converte elementos do Overpass em features GeoJSON simplificadas.
     *
     * @param  array<int, array<string, mixed>>  $elements
     * @return array<int, array{type: string, geometry: array<string, mixed>}>
     */
    private function buildFeatures(array $elements): array
    {
        // Separar nodes e ways
        $nodes = [];
        $ways = [];

        foreach ($elements as $el) {
            if (($el['type'] ?? '') === 'node') {
                $nodes[$el['id']] = ['lat' => $el['lat'], 'lng' => $el['lon']];
            } elseif (($el['type'] ?? '') === 'way') {
                $ways[] = $el;
            }
        }

        $features = [];

        foreach ($ways as $way) {
            $tags = $way['tags'] ?? [];
            $nodeIds = $way['nodes'] ?? [];

            // Determinar tipo
            $type = $this->classifyFeature($tags);

            // Extrair coordenadas dos nodes
            $coords = [];
            foreach ($nodeIds as $nid) {
                if (isset($nodes[$nid])) {
                    $coords[] = $nodes[$nid];
                }
            }

            if (count($coords) < 2) {
                continue;
            }

            $features[] = [
                'type' => $type,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => array_map(fn (array $c) => [$c['lng'], $c['lat']], $coords),
                ],
            ];
        }

        // Adicionar nascentes (nodes isolados)
        foreach ($elements as $el) {
            if (($el['type'] ?? '') === 'node' && isset($el['tags']['natural']) && $el['tags']['natural'] === 'spring') {
                $features[] = [
                    'type' => 'natural_spring',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [$el['lon'], $el['lat']],
                    ],
                ];
            }
        }

        return $features;
    }

    /**
     * Classifica uma feature baseado em tags do OpenStreetMap.
     *
     * @param  array<string, string>  $tags
     */
    private function classifyFeature(array $tags): string
    {
        if (isset($tags['natural']) && $tags['natural'] === 'spring') {
            return 'natural_spring';
        }

        if (isset($tags['natural']) && $tags['natural'] === 'water') {
            return 'natural_water';
        }

        $waterway = $tags['waterway'] ?? '';

        return match ($waterway) {
            'river' => 'waterway_river',
            'stream' => 'waterway_stream',
            'canal' => 'waterway_canal',
            'ditch' => 'waterway_ditch',
            default => 'default',
        };
    }

    /**
     * Extrai coordenadas de uma geometria GeoJSON.
     *
     * @return array<int, array{lat: float, lng: float}>
     */
    private function extractCoordinates(array $geometry): array
    {
        $type = $geometry['type'] ?? 'Point';
        $rawCoords = $geometry['coordinates'] ?? [];

        $points = [];

        if ($type === 'Point') {
            if (count($rawCoords) >= 2) {
                $points[] = ['lng' => (float) $rawCoords[0], 'lat' => (float) $rawCoords[1]];
            }
        } elseif ($type === 'LineString') {
            foreach ($rawCoords as $coord) {
                if (count($coord) >= 2) {
                    $points[] = ['lng' => (float) $coord[0], 'lat' => (float) $coord[1]];
                }
            }
        } elseif ($type === 'MultiLineString') {
            foreach ($rawCoords as $line) {
                foreach ($line as $coord) {
                    if (count($coord) >= 2) {
                        $points[] = ['lng' => (float) $coord[0], 'lat' => (float) $coord[1]];
                    }
                }
            }
        }

        return $points;
    }

    /**
     * Algoritmo de ray casting: verifica se um ponto está dentro de um polígono.
     *
     * @param  array{lat: float, lng: float}  $point
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     */
    private function pointInPolygon(array $point, array $polygon): bool
    {
        $x = $point['lng'];
        $y = $point['lat'];
        $inside = false;
        $n = count($polygon);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $polygon[$i]['lng'];
            $yi = $polygon[$i]['lat'];
            $xj = $polygon[$j]['lng'];
            $yj = $polygon[$j]['lat'];

            if (($yi > $y) !== ($yj > $y)
                && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi
            ) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * @param  array{south: float, north: float, west: float, east: float}  $bbox
     * @return array{south: float, north: float, west: float, east: float}
     */
    private function expandBbox(array $bbox, float $meters): array
    {
        $meanLat = ($bbox['south'] + $bbox['north']) / 2;
        $cosLat = cos(deg2rad($meanLat));

        $deltaLat = $meters / 110_540.0;
        $deltaLng = $meters / (111_320.0 * max($cosLat, 0.01));

        return [
            'south' => $bbox['south'] - $deltaLat,
            'north' => $bbox['north'] + $deltaLat,
            'west' => $bbox['west'] - $deltaLng,
            'east' => $bbox['east'] + $deltaLng,
        ];
    }
}
