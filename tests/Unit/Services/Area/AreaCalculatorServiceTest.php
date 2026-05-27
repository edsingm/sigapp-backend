<?php

namespace Tests\Unit\Services\Area;

use App\Models\Tenant\Terreno;
use App\Services\Tenant\Area\AreaCalculatorService;
use App\Services\Tenant\Area\HydrographyService;
use App\Services\Tenant\Area\PolygonCalculator;
use App\Services\Tenant\Area\TopographyService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AreaCalculatorServiceTest extends TestCase
{
    private PolygonCalculator $polygonCalc;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.elevation.provider', 'google');
        Config::set('services.google_maps.key', 'fake-key');

        $this->polygonCalc = new PolygonCalculator;
    }

    private function makeTerreno(array $polygon): Terreno
    {
        $terreno = $this->createMock(Terreno::class);
        $terreno->method('__get')->willReturnCallback(
            fn (string $key) => $key === 'polygon_coords' ? $polygon : null,
        );

        return $terreno;
    }

    public function test_calculate_returns_full_result_for_valid_polygon(): void
    {
        $topography = $this->createMock(TopographyService::class);
        $hydrography = $this->createMock(HydrographyService::class);

        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5000, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6000],
        ];

        $topography->method('getElevationsForPolygon')
            ->willReturn([
                ['lat' => -23.5000, 'lng' => -46.6000, 'elevation' => 800.0],
                ['lat' => -23.5000, 'lng' => -46.6010, 'elevation' => 810.0],
                ['lat' => -23.5010, 'lng' => -46.6010, 'elevation' => 820.0],
                ['lat' => -23.5010, 'lng' => -46.6000, 'elevation' => 830.0],
            ]);

        $topography->method('calculateSlopes')
            ->willReturn([
                ['lat' => -23.5000, 'lng' => -46.6010, 'slope' => 5.0],
                ['lat' => -23.5010, 'lng' => -46.6010, 'slope' => 8.0],
                ['lat' => -23.5010, 'lng' => -46.6000, 'slope' => 35.0],
            ]);

        $hydrography->method('calculateAppArea')->willReturn([
            'area' => 500.0,
            'polygons' => [],
        ]);

        $service = new AreaCalculatorService(
            $this->polygonCalc,
            $topography,
            $hydrography,
        );

        $result = $service->calculate($this->makeTerreno($polygon));

        $this->assertArrayHasKey('area_total', $result);
        $this->assertArrayHasKey('area_declividade', $result);
        $this->assertArrayHasKey('area_app', $result);
        $this->assertArrayHasKey('area_util', $result);
        $this->assertArrayHasKey('percentual_aproveitamento', $result);
        $this->assertArrayHasKey('app_polygons', $result);
        $this->assertArrayHasKey('steep_polygons', $result);

        $this->assertGreaterThan(0.0, $result['area_total']);
        $this->assertGreaterThan(0.0, $result['area_app']);
        $this->assertGreaterThan(0.0, $result['area_util']);
    }

    public function test_calculate_returns_zeros_for_empty_polygon(): void
    {
        $topography = $this->createMock(TopographyService::class);
        $hydrography = $this->createMock(HydrographyService::class);

        $service = new AreaCalculatorService(
            $this->polygonCalc,
            $topography,
            $hydrography,
        );

        $result = $service->calculate($this->makeTerreno([]));

        $this->assertSame([
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
        ], $result);
    }

    public function test_calculate_handles_topo_failure_gracefully(): void
    {
        $topography = $this->createMock(TopographyService::class);
        $hydrography = $this->createMock(HydrographyService::class);

        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5000, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6000],
        ];

        $topography->method('getElevationsForPolygon')
            ->willThrowException(new \RuntimeException('API indisponível'));

        $hydrography->method('calculateAppArea')->willReturn([
            'area' => 0.0,
            'polygons' => [],
        ]);

        $service = new AreaCalculatorService(
            $this->polygonCalc,
            $topography,
            $hydrography,
        );

        $result = $service->calculate($this->makeTerreno($polygon));

        $this->assertGreaterThan(0.0, $result['area_total']);
        $this->assertSame(0.0, $result['area_declividade']);
        $this->assertSame(0.0, $result['area_app']);
        $this->assertEquals($result['area_total'], $result['area_util']);
    }

    public function test_calculate_percentual_aproveitamento_is_correct(): void
    {
        $topography = $this->createMock(TopographyService::class);
        $hydrography = $this->createMock(HydrographyService::class);

        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5000, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6000],
        ];

        $topography->method('getElevationsForPolygon')
            ->willReturn([
                ['lat' => -23.5000, 'lng' => -46.6000, 'elevation' => 800.0],
                ['lat' => -23.5000, 'lng' => -46.6010, 'elevation' => 800.0],
                ['lat' => -23.5010, 'lng' => -46.6010, 'elevation' => 800.0],
                ['lat' => -23.5010, 'lng' => -46.6000, 'elevation' => 800.0],
            ]);

        $topography->method('calculateSlopes')
            ->willReturn([
                ['lat' => -23.5000, 'lng' => -46.6010, 'slope' => 0.0],
                ['lat' => -23.5010, 'lng' => -46.6010, 'slope' => 0.0],
                ['lat' => -23.5010, 'lng' => -46.6000, 'slope' => 0.0],
            ]);

        $hydrography->method('calculateAppArea')->willReturn([
            'area' => 0.0,
            'polygons' => [],
        ]);

        $service = new AreaCalculatorService(
            $this->polygonCalc,
            $topography,
            $hydrography,
        );

        $result = $service->calculate($this->makeTerreno($polygon));

        $this->assertEqualsWithDelta(100.0, $result['percentual_aproveitamento'], 0.01);
        $this->assertEqualsWithDelta($result['area_total'], $result['area_util'], 0.01);
    }
}
