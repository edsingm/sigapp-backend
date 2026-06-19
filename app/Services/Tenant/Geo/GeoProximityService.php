<?php

namespace App\Services\Tenant\Geo;

use App\Services\Tenant\Area\PolygonCalculator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeoProximityService
{
    private const PLACES_API = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';

    private const GEOCODING_API = 'https://maps.googleapis.com/maps/api/geocode/json';

    /**
     * Um tipo do Google Places por categoria.
     * A Places API aceita apenas um type por requisição.
     */
    private const PLACE_TYPES = [
        'escola' => 'school',
        'universidade' => 'university',
        'hospital' => 'hospital',
        'clinica' => 'doctor',
        'farmacia' => 'pharmacy',
        'mercado' => 'supermarket',
        'banco' => 'bank',
        'posto_gasolina' => 'gas_station',
    ];

    public function __construct(
        private readonly PolygonCalculator $polygonCalc,
    ) {}

    /**
     * Retorna vias próximas usando reverse geocoding em pontos amostrados do polígono.
     *
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     * @return array<int, array{name: string, type: string}>
     */
    public function getNearbyRoads(array $polygon, int $radiusMeters = 1000): array
    {
        $center = $this->polygonCalc->centroid($polygon);
        $cacheKey = "geo_roads_{$center['lat']}_{$center['lng']}_{$radiusMeters}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($polygon, $center): array {
            $apiKey = $this->resolveApiKey();

            // Amostra: centroide + até 4 vértices distribuídos ao longo do polígono
            $samplePoints = $this->samplePolygonPoints($polygon, $center, 4);

            $routes = [];

            foreach ($samplePoints as $point) {
                $results = $this->reverseGeocode($point, $apiKey);

                foreach ($results as $component) {
                    if (in_array('route', $component['types'], true)) {
                        $name = $component['long_name'];

                        if ($name && ! isset($routes[$name])) {
                            $routes[$name] = [
                                'name' => $name,
                                'type' => $this->classifyRoadByName($name),
                            ];
                        }
                    }
                }
            }

            return array_values($routes);
        });
    }

    /**
     * Retorna pontos de apoio próximos agrupados por categoria, via Google Places API.
     *
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     * @return array<string, array<int, array{name: string|null, distancia_metros: int, lat: float, lng: float}>>
     */
    public function getNearbyAmenities(array $polygon, int $radiusMeters = 1000): array
    {
        $center = $this->polygonCalc->centroid($polygon);
        $cacheKey = "geo_amenities_{$center['lat']}_{$center['lng']}_{$radiusMeters}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($center, $radiusMeters): array {
            $apiKey = $this->resolveApiKey();
            $grouped = [];

            foreach (self::PLACE_TYPES as $categoria => $type) {
                $places = $this->searchNearbyPlaces($center, $radiusMeters, $type, $apiKey);

                if (empty($places)) {
                    continue;
                }

                $entries = [];

                foreach (array_slice($places, 0, 5) as $place) {
                    $placeLat = $place['geometry']['location']['lat'] ?? null;
                    $placeLng = $place['geometry']['location']['lng'] ?? null;

                    if ($placeLat === null || $placeLng === null) {
                        continue;
                    }

                    $entries[] = [
                        'name' => $place['name'] ?? null,
                        'distancia_metros' => (int) round(
                            $this->polygonCalc->haversineDistance(
                                $center,
                                ['lat' => (float) $placeLat, 'lng' => (float) $placeLng],
                            )
                        ),
                        'lat' => (float) $placeLat,
                        'lng' => (float) $placeLng,
                    ];
                }

                if (! empty($entries)) {
                    usort($entries, fn ($a, $b) => $a['distancia_metros'] <=> $b['distancia_metros']);
                    $grouped[$categoria] = $entries;
                }
            }

            return $grouped;
        });
    }

    // ── Internos ─────────────────────────────────────────────────────

    private function resolveApiKey(): string
    {
        $key = config('services.google_maps.key');

        if (empty($key)) {
            throw new RuntimeException('Google Maps API key não configurada (GOOGLE_MAPS_KEY).');
        }

        return $key;
    }

    /**
     * Retorna centroide + vértices amostrados de forma uniforme.
     *
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     * @param  array{lat: float, lng: float}  $center
     * @return array<int, array{lat: float, lng: float}>
     */
    private function samplePolygonPoints(array $polygon, array $center, int $count): array
    {
        $points = [$center];
        $n = count($polygon);

        if ($n === 0) {
            return $points;
        }

        $step = max(1, (int) floor($n / $count));

        for ($i = 0; $i < $n && count($points) <= $count; $i += $step) {
            $points[] = $polygon[$i];
        }

        return $points;
    }

    /**
     * @param  array{lat: float, lng: float}  $point
     * @return array<int, array{types: list<string>, long_name: string}>
     */
    private function reverseGeocode(array $point, string $apiKey): array
    {
        try {
            $response = Http::timeout(10)->get(self::GEOCODING_API, [
                'latlng' => "{$point['lat']},{$point['lng']}",
                'result_type' => 'route',
                'key' => $apiKey,
            ]);

            if ($response->failed() || ($response->json('status') !== 'OK' && $response->json('status') !== 'ZERO_RESULTS')) {
                Log::warning('Google Geocoding API falhou', ['status' => $response->json('status')]);

                return [];
            }

            $components = [];

            foreach ($response->json('results', []) as $result) {
                foreach ($result['address_components'] ?? [] as $component) {
                    $components[] = $component;
                }
            }

            return $components;

        } catch (\Throwable $e) {
            Log::warning('Google Geocoding API erro', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array{lat: float, lng: float}  $center
     * @return array<int, mixed>
     */
    private function searchNearbyPlaces(array $center, int $radius, string $type, string $apiKey): array
    {
        try {
            $response = Http::timeout(15)->get(self::PLACES_API, [
                'location' => "{$center['lat']},{$center['lng']}",
                'radius' => $radius,
                'type' => $type,
                'key' => $apiKey,
            ]);

            $status = $response->json('status');

            if ($response->failed() || ! in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
                Log::warning('Google Places API falhou', ['type' => $type, 'status' => $status]);

                return [];
            }

            return $response->json('results', []);

        } catch (\Throwable $e) {
            Log::warning('Google Places API erro', ['type' => $type, 'error' => $e->getMessage()]);

            return [];
        }
    }

    private function classifyRoadByName(string $name): string
    {
        $lower = mb_strtolower($name);

        if (str_contains($lower, 'rodovia') || str_contains($lower, 'br-') || str_contains($lower, 'sp-') || str_contains($lower, 'mg-')) {
            return 'rodovia';
        }

        if (str_contains($lower, 'avenida') || str_contains($lower, 'av.')) {
            return 'avenida';
        }

        if (str_contains($lower, 'estrada')) {
            return 'estrada';
        }

        if (str_contains($lower, 'alameda') || str_contains($lower, 'alm.')) {
            return 'alameda';
        }

        return 'rua';
    }
}
