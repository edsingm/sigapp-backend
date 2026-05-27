<?php

namespace Tests\Unit\Services\Area;

use App\Services\Tenant\Area\HydrographyService;
use App\Services\Tenant\Area\PolygonCalculator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HydrographyServiceTest extends TestCase
{
    private HydrographyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new HydrographyService(new PolygonCalculator);
    }

    public function test_calculate_app_area_returns_array_with_zero_for_empty_polygon(): void
    {
        $result = $this->service->calculateAppArea([]);

        $this->assertArrayHasKey('area', $result);
        $this->assertArrayHasKey('polygons', $result);
        $this->assertSame(0.0, $result['area']);
        $this->assertEmpty($result['polygons']);
    }

    public function test_calculate_app_area_returns_zero_when_no_water_bodies(): void
    {
        // Mock Overpass API retornando vazio
        Http::fake([
            'overpass-api.de/*' => Http::response(['elements' => []], 200),
        ]);

        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5000, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6000],
        ];

        Cache::tags(['hydrography'])->flush();

        $result = $this->service->calculateAppArea($polygon);

        $this->assertSame(0.0, $result['area']);
        $this->assertEmpty($result['polygons']);
    }

    public function test_calculate_app_area_returns_positive_when_water_bodies_found(): void
    {
        // Rio com um ponto claramente dentro do polígono do terreno
        Http::fake([
            'overpass-api.de/*' => Http::response([
                'elements' => [
                    [
                        'type' => 'node',
                        'id' => 1,
                        'lat' => -23.5007,
                        'lon' => -46.6012,
                    ],
                    [
                        'type' => 'node',
                        'id' => 2,
                        'lat' => -23.5006,
                        'lon' => -46.6013,
                    ],
                    [
                        'type' => 'node',
                        'id' => 3,
                        'lat' => -23.5005,
                        'lon' => -46.6014,
                    ],
                    [
                        'type' => 'way',
                        'id' => 100,
                        'nodes' => [1, 2, 3],
                        'tags' => ['waterway' => 'river', 'name' => 'Rio Teste'],
                    ],
                ],
            ], 200),
        ]);

        // Terreno que contém os pontos do rio
        $polygon = [
            ['lat' => -23.5009, 'lng' => -46.6010],
            ['lat' => -23.5009, 'lng' => -46.6015],
            ['lat' => -23.5005, 'lng' => -46.6015],
            ['lat' => -23.5005, 'lng' => -46.6010],
        ];

        Cache::tags(['hydrography'])->flush();

        $result = $this->service->calculateAppArea($polygon);

        $this->assertGreaterThan(0.0, $result['area']);
        $this->assertNotEmpty($result['polygons']);
        $this->assertArrayHasKey('type', $result['polygons'][0]);
        $this->assertArrayHasKey('coordinates', $result['polygons'][0]);
        $this->assertSame('Polygon', $result['polygons'][0]['type']);
    }

    public function test_get_water_bodies_caches_results(): void
    {
        Http::fake([
            'overpass-api.de/*' => Http::response(['elements' => []], 200),
        ]);

        $polygon = [
            ['lat' => -23.50, 'lng' => -46.60],
            ['lat' => -23.51, 'lng' => -46.61],
        ];

        Cache::tags(['hydrography'])->flush();

        // Primeira chamada
        $this->service->getWaterBodiesForPolygon($polygon);

        // Segunda chamada — deve vir do cache, não da API
        Http::assertSentCount(1);

        $this->service->getWaterBodiesForPolygon($polygon);

        Http::assertSentCount(1);
    }

    public function test_get_water_bodies_handles_overpass_failure(): void
    {
        Http::fake([
            'overpass-api.de/*' => Http::response('Service Unavailable', 503),
        ]);

        $polygon = [
            ['lat' => -23.50, 'lng' => -46.60],
            ['lat' => -23.51, 'lng' => -46.61],
        ];

        Cache::tags(['hydrography'])->flush();

        $result = $this->service->getWaterBodiesForPolygon($polygon);

        $this->assertEmpty($result);
    }
}
