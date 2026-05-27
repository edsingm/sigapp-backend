<?php

namespace App\Services\Tenant\Area;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Consulta dados de elevação para pontos geográficos.
 *
 * Estratégia em camadas:
 *  1. Google Elevation API (JSON, simples, pago ~$5/1000 req)
 *  2. OpenTopography SRTM (GeoTIFF, gratuito, parsing mais complexo)
 *
 * Ambos retornam [{lat, lng, elevation}, ...] já normalizados.
 */
class TopographyService
{
    private const GOOGLE_ELEVATION_API = 'https://maps.googleapis.com/maps/api/elevation/json';

    private const OPENTOPO_API = 'https://portal.opentopography.org/API/globaldem';

    private const OPEN_ELEVATION_API = 'https://api.open-elevation.com/api/v1/lookup';

    public function __construct(
        private readonly PolygonCalculator $polygonCalc,
    ) {}

    /**
     * Busca elevações para os vértices de um polígono.
     *
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     * @return array<int, array{lat: float, lng: float, elevation: float}>
     */
    public function getElevationsForPolygon(array $polygon): array
    {
        $provider = config('services.elevation.provider', 'open-elevation');

        return match ($provider) {
            'google' => $this->getElevationsGoogle($polygon),
            'opentopo' => $this->getElevationsOpenTopography($polygon),
            'open-elevation' => $this->getElevationsOpenElevation($polygon),
            default => throw new RuntimeException("Provider de elevação desconhecido: {$provider}"),
        };
    }

    /**
     * Calcula a declividade ponto a ponto a partir de uma lista de elevações.
     *
     * @param  array<int, array{lat: float, lng: float, elevation: float}>  $elevations
     * @return array<int, array{lat: float, lng: float, slope: float}>
     */
    public function calculateSlopes(array $elevations): array
    {
        $slopes = [];
        $count = count($elevations);

        for ($i = 1; $i < $count; $i++) {
            $prev = $elevations[$i - 1];
            $curr = $elevations[$i];

            $slope = $this->polygonCalc->slopePercent(
                $prev,
                $curr,
                $curr['elevation'] - $prev['elevation'],
            );

            $slopes[] = [
                'lat' => $curr['lat'],
                'lng' => $curr['lng'],
                'slope' => $slope,
            ];
        }

        return $slopes;
    }

    // ----------------------------------------------------------------
    //  Google Elevation API
    // ----------------------------------------------------------------

    /**
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     * @return array<int, array{lat: float, lng: float, elevation: float}>
     */
    private function getElevationsGoogle(array $polygon): array
    {
        $apiKey = config('services.google_maps.key');

        if (empty($apiKey)) {
            throw new RuntimeException('Google Maps API key não configurada (GOOGLE_MAPS_KEY).');
        }

        // Google aceita até 512 pontos por request
        $chunks = array_chunk($polygon, 500);
        $results = [];

        foreach ($chunks as $chunk) {
            $locations = implode('|', array_map(
                fn (array $p) => "{$p['lat']},{$p['lng']}",
                $chunk,
            ));

            $response = Http::timeout(30)->get(self::GOOGLE_ELEVATION_API, [
                'locations' => $locations,
                'key' => $apiKey,
            ]);

            if ($response->failed()) {
                Log::error('Google Elevation API falhou', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new RuntimeException('Erro ao consultar Google Elevation API.');
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK') {
                throw new RuntimeException("Google Elevation retornou status: {$data['status']}");
            }

            foreach ($data['results'] as $item) {
                $results[] = [
                    'lat' => $item['location']['lat'],
                    'lng' => $item['location']['lng'],
                    'elevation' => (float) $item['elevation'],
                ];
            }
        }

        return $results;
    }

    // ----------------------------------------------------------------
    //  OpenTopography (SRTM GL1 — 30 m)
    // ----------------------------------------------------------------

    /**
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     * @return array<int, array{lat: float, lng: float, elevation: float}>
     */
    private function getElevationsOpenTopography(array $polygon): array
    {
        $apiKey = config('services.opentopography.key');

        if (empty($apiKey)) {
            throw new RuntimeException('OpenTopography API key não configurada (OPENTOPOGRAPHY_KEY).');
        }

        $bbox = $this->polygonCalc->boundingBox($polygon);

        $response = Http::timeout(60)->get(self::OPENTOPO_API, [
            'demtype' => 'SRTMGL1',
            'south' => $bbox['south'],
            'north' => $bbox['north'],
            'west' => $bbox['west'],
            'east' => $bbox['east'],
            'outputFormat' => 'JSON',
            'API_Key' => $apiKey,
        ]);

        if ($response->failed()) {
            Log::error('OpenTopography API falhou', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Erro ao consultar OpenTopography API.');
        }

        $data = $response->json();

        // A resposta do OpenTopography JSON varia conforme o dataset.
        // Para SRTM GL1 JSON: { "data": [[lng, lat, elev], ...], ... }
        $rawData = $data['data'] ?? $data['result'] ?? [];

        $results = [];

        foreach ($rawData as $row) {
            if (count($row) >= 3) {
                $results[] = [
                    'lng' => (float) $row[0],
                    'lat' => (float) $row[1],
                    'elevation' => (float) $row[2],
                ];
            }
        }

        return $results;
    }

    // ----------------------------------------------------------------
    //  Open-Elevation (gratuito, sem API key, SRTM-based)
    // ----------------------------------------------------------------

    /**
     * @param  array<int, array{lat: float, lng: float}>  $polygon
     * @return array<int, array{lat: float, lng: float, elevation: float}>
     */
    private function getElevationsOpenElevation(array $polygon): array
    {
        $locations = array_map(
            fn (array $p) => ['latitude' => $p['lat'], 'longitude' => $p['lng']],
            $polygon,
        );

        $response = Http::timeout(60)
            ->asJson()
            ->post(self::OPEN_ELEVATION_API, ['locations' => $locations]);

        if ($response->failed()) {
            Log::error('Open-Elevation API falhou', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Erro ao consultar Open-Elevation API.');
        }

        $data = $response->json();

        if (empty($data) || ! isset($data['results'])) {
            throw new RuntimeException('Resposta inválida da Open-Elevation API.');
        }

        return array_map(
            fn (array $r) => [
                'lat' => (float) $r['latitude'],
                'lng' => (float) $r['longitude'],
                'elevation' => (float) $r['elevation'],
            ],
            $data['results'],
        );
    }
}
