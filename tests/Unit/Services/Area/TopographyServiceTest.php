<?php

namespace Tests\Unit\Services\Area;

use App\Services\Tenant\Area\PolygonCalculator;
use App\Services\Tenant\Area\TopographyService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TopographyServiceTest extends TestCase
{
    private PolygonCalculator $polygonCalc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->polygonCalc = new PolygonCalculator;
    }

    // ----------------------------------------------------------------
    //  Google Elevation API
    // ----------------------------------------------------------------

    public function test_google_elevation_returns_elevations(): void
    {
        Config::set('services.elevation.provider', 'google');
        Config::set('services.google_maps.key', 'fake-key');

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [
                    ['location' => ['lat' => -23.5000, 'lng' => -46.6000], 'elevation' => 800.0],
                    ['location' => ['lat' => -23.5010, 'lng' => -46.6010], 'elevation' => 820.0],
                ],
            ], 200),
        ]);

        $service = new TopographyService($this->polygonCalc);
        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5010, 'lng' => -46.6010],
        ];

        $result = $service->getElevationsForPolygon($polygon);

        $this->assertCount(2, $result);
        $this->assertEqualsWithDelta(800.0, $result[0]['elevation'], 0.01);
        $this->assertEqualsWithDelta(820.0, $result[1]['elevation'], 0.01);
    }

    public function test_google_elevation_throws_when_api_key_missing(): void
    {
        Config::set('services.elevation.provider', 'google');
        Config::set('services.google_maps.key', null);

        $service = new TopographyService($this->polygonCalc);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Maps API key não configurada');

        $service->getElevationsForPolygon([['lat' => -23.5, 'lng' => -46.6]]);
    }

    public function test_google_elevation_throws_on_api_failure(): void
    {
        Config::set('services.elevation.provider', 'google');
        Config::set('services.google_maps.key', 'fake-key');

        Http::fake([
            'maps.googleapis.com/*' => Http::response('Server Error', 500),
        ]);

        $service = new TopographyService($this->polygonCalc);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Erro ao consultar Google Elevation API');

        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5010, 'lng' => -46.6010],
        ];

        $service->getElevationsForPolygon($polygon);
    }

    // ----------------------------------------------------------------
    //  OpenTopography API
    // ----------------------------------------------------------------

    public function test_opentopo_elevation_returns_elevations(): void
    {
        Config::set('services.elevation.provider', 'opentopo');
        Config::set('services.opentopography.key', 'fake-key');

        Http::fake([
            'portal.opentopography.org/*' => Http::response([
                'data' => [
                    [-46.6000, -23.5000, 800.0],
                    [-46.6010, -23.5010, 820.0],
                ],
            ], 200),
        ]);

        $service = new TopographyService($this->polygonCalc);
        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5010, 'lng' => -46.6010],
        ];

        $result = $service->getElevationsForPolygon($polygon);

        $this->assertCount(2, $result);
        $this->assertEqualsWithDelta(800.0, $result[0]['elevation'], 0.01);
        $this->assertEqualsWithDelta(820.0, $result[1]['elevation'], 0.01);
    }

    public function test_opentopo_elevation_throws_when_api_key_missing(): void
    {
        Config::set('services.elevation.provider', 'opentopo');
        Config::set('services.opentopography.key', null);

        $service = new TopographyService($this->polygonCalc);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenTopography API key não configurada');

        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5010, 'lng' => -46.6010],
        ];

        $service->getElevationsForPolygon($polygon);
    }

    public function test_opentopo_elevation_throws_on_api_failure(): void
    {
        Config::set('services.elevation.provider', 'opentopo');
        Config::set('services.opentopography.key', 'fake-key');

        Http::fake([
            'portal.opentopography.org/*' => Http::response('Server Error', 500),
        ]);

        $service = new TopographyService($this->polygonCalc);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Erro ao consultar OpenTopography API');

        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5010, 'lng' => -46.6010],
        ];

        $service->getElevationsForPolygon($polygon);
    }

    // ----------------------------------------------------------------
    //  calculateSlopes
    // ----------------------------------------------------------------

    public function test_calculate_slopes_returns_correct_values(): void
    {
        $service = new TopographyService($this->polygonCalc);

        // ~100m entre pontos, 10m de elevação → ~10%
        $elevations = [
            ['lat' => -23.5000, 'lng' => -46.6000, 'elevation' => 800.0],
            ['lat' => -23.5009, 'lng' => -46.6000, 'elevation' => 810.0],
        ];

        $slopes = $service->calculateSlopes($elevations);

        $this->assertCount(1, $slopes);
        $this->assertGreaterThan(8.0, $slopes[0]['slope']);
        $this->assertLessThan(12.0, $slopes[0]['slope']);
    }

    public function test_calculate_slopes_returns_empty_for_single_point(): void
    {
        $service = new TopographyService($this->polygonCalc);

        $slopes = $service->calculateSlopes([
            ['lat' => -23.5, 'lng' => -46.6, 'elevation' => 800.0],
        ]);

        $this->assertEmpty($slopes);
    }

    public function test_calculate_slopes_returns_empty_for_empty_array(): void
    {
        $service = new TopographyService($this->polygonCalc);

        $this->assertEmpty($service->calculateSlopes([]));
    }

    // ----------------------------------------------------------------
    //  Open-Elevation API
    // ----------------------------------------------------------------

    public function test_open_elevation_returns_elevations_for_polygon_vertices(): void
    {
        Config::set('services.elevation.provider', 'open-elevation');

        Http::fake([
            'api.open-elevation.com/*' => Http::response([
                'results' => [
                    ['latitude' => -23.5000, 'longitude' => -46.6000, 'elevation' => 800.0],
                    ['latitude' => -23.5010, 'longitude' => -46.6010, 'elevation' => 820.0],
                ],
            ], 200),
        ]);

        $service = new TopographyService($this->polygonCalc);
        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5010, 'lng' => -46.6010],
        ];

        $result = $service->getElevationsForPolygon($polygon);

        $this->assertCount(2, $result);
        $this->assertEqualsWithDelta(800.0, $result[0]['elevation'], 0.01);
        $this->assertEqualsWithDelta(-23.5000, $result[0]['lat'], 0.0001);
        $this->assertEqualsWithDelta(-46.6000, $result[0]['lng'], 0.0001);
        $this->assertEqualsWithDelta(820.0, $result[1]['elevation'], 0.01);
    }

    public function test_open_elevation_throws_on_api_failure(): void
    {
        Config::set('services.elevation.provider', 'open-elevation');

        Http::fake([
            'api.open-elevation.com/*' => Http::response('Server Error', 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Erro ao consultar Open-Elevation API');

        $service = new TopographyService($this->polygonCalc);
        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5010, 'lng' => -46.6010],
        ];

        $service->getElevationsForPolygon($polygon);
    }

    public function test_open_elevation_sends_post_with_correct_payload(): void
    {
        Config::set('services.elevation.provider', 'open-elevation');

        Http::fake([
            'api.open-elevation.com/*' => Http::response(['results' => []], 200),
        ]);

        $service = new TopographyService($this->polygonCalc);
        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5010, 'lng' => -46.6010],
        ];

        $service->getElevationsForPolygon($polygon);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $locations = $body['locations'] ?? [];

            return $request->method() === 'POST'
                && count($locations) === 2
                && $locations[0]['latitude'] === -23.5000
                && $locations[0]['longitude'] === -46.6000
                && $locations[1]['latitude'] === -23.5010
                && $locations[1]['longitude'] === -46.6010;
        });
    }

    // ----------------------------------------------------------------
    //  Provider inválido
    // ----------------------------------------------------------------

    public function test_unknown_provider_throws_exception(): void
    {
        Config::set('services.elevation.provider', 'invalid_provider');

        $service = new TopographyService($this->polygonCalc);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provider de elevação desconhecido');

        $service->getElevationsForPolygon([['lat' => -23.5, 'lng' => -46.6]]);
    }
}
