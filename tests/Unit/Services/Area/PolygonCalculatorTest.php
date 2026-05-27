<?php

namespace Tests\Unit\Services\Area;

use App\Services\Tenant\Area\PolygonCalculator;
use PHPUnit\Framework\TestCase;

class PolygonCalculatorTest extends TestCase
{
    private PolygonCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PolygonCalculator;
    }

    // ----------------------------------------------------------------
    //  calculateArea — Shoelace formula
    // ----------------------------------------------------------------

    public function test_calculate_area_returns_zero_for_less_than_three_points(): void
    {
        $this->assertSame(0.0, $this->calculator->calculateArea([]));
        $this->assertSame(0.0, $this->calculator->calculateArea([
            ['lat' => -23.5, 'lng' => -46.6],
        ]));
        $this->assertSame(0.0, $this->calculator->calculateArea([
            ['lat' => -23.5, 'lng' => -46.6],
            ['lat' => -23.6, 'lng' => -46.7],
        ]));
    }

    public function test_calculate_area_returns_positive_value_for_triangle(): void
    {
        // Triângulo ~100m x ~100m perto de São Paulo
        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5000, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6000],
        ];

        $area = $this->calculator->calculateArea($polygon);

        $this->assertGreaterThan(0.0, $area);
        // Área esperada: ~5.000 m² (triângulo retângulo 100m x 100m / 2)
        $this->assertGreaterThan(4_000, $area);
        $this->assertLessThan(6_000, $area);
    }

    public function test_calculate_area_returns_positive_value_for_square(): void
    {
        // Quadrado ~100m x ~100m perto de São Paulo
        $polygon = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5000, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6000],
        ];

        $area = $this->calculator->calculateArea($polygon);

        $this->assertGreaterThan(0.0, $area);
        // Área esperada: ~10.000 m² (100m x 100m)
        $this->assertGreaterThan(8_000, $area);
        $this->assertLessThan(12_000, $area);
    }

    public function test_calculate_area_is_clockwise_invariant(): void
    {
        // Mesmo polígono em sentido horário e anti-horário deve ter mesma área
        $cw = [
            ['lat' => -23.5000, 'lng' => -46.6000],
            ['lat' => -23.5000, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6010],
            ['lat' => -23.5010, 'lng' => -46.6000],
        ];

        $ccw = array_reverse($cw);

        $this->assertEqualsWithDelta(
            $this->calculator->calculateArea($cw),
            $this->calculator->calculateArea($ccw),
            0.01,
        );
    }

    // ----------------------------------------------------------------
    //  haversineDistance
    // ----------------------------------------------------------------

    public function test_haversine_distance_returns_zero_for_same_point(): void
    {
        $p = ['lat' => -23.5, 'lng' => -46.6];

        $this->assertEqualsWithDelta(0.0, $this->calculator->haversineDistance($p, $p), 0.01);
    }

    public function test_haversine_distance_returns_correct_distance(): void
    {
        // Distância entre dois pontos ~1km em São Paulo
        $p1 = ['lat' => -23.5000, 'lng' => -46.6000];
        $p2 = ['lat' => -23.5090, 'lng' => -46.6000];

        $distance = $this->calculator->haversineDistance($p1, $p2);

        // ~1km (0.009 graus de latitude ≈ 995m)
        $this->assertGreaterThan(900, $distance);
        $this->assertLessThan(1100, $distance);
    }

    // ----------------------------------------------------------------
    //  slopePercent
    // ----------------------------------------------------------------

    public function test_slope_percent_returns_zero_for_flat_terrain(): void
    {
        $p1 = ['lat' => -23.5, 'lng' => -46.6];
        $p2 = ['lat' => -23.5001, 'lng' => -46.6];

        $this->assertEqualsWithDelta(0.0, $this->calculator->slopePercent($p1, $p2, 0.0), 0.01);
    }

    public function test_slope_percent_returns_correct_value(): void
    {
        $p1 = ['lat' => -23.5000, 'lng' => -46.6000];
        $p2 = ['lat' => -23.5090, 'lng' => -46.6000]; // ~1km

        // 100m de elevação em 1km = 10%
        $slope = $this->calculator->slopePercent($p1, $p2, 100.0);

        $this->assertEqualsWithDelta(10.0, $slope, 0.5);
    }

    // ----------------------------------------------------------------
    //  boundingBox
    // ----------------------------------------------------------------

    public function test_bounding_box_returns_correct_bounds(): void
    {
        $polygon = [
            ['lat' => -23.50, 'lng' => -46.60],
            ['lat' => -23.51, 'lng' => -46.61],
            ['lat' => -23.49, 'lng' => -46.59],
        ];

        $bbox = $this->calculator->boundingBox($polygon);

        $this->assertEqualsWithDelta(-23.51, $bbox['south'], 0.001);
        $this->assertEqualsWithDelta(-23.49, $bbox['north'], 0.001);
        $this->assertEqualsWithDelta(-46.61, $bbox['west'], 0.001);
        $this->assertEqualsWithDelta(-46.59, $bbox['east'], 0.001);
    }

    public function test_bounding_box_returns_zeros_for_empty_polygon(): void
    {
        $bbox = $this->calculator->boundingBox([]);

        $this->assertSame(0.0, $bbox['south']);
        $this->assertSame(0.0, $bbox['north']);
        $this->assertSame(0.0, $bbox['west']);
        $this->assertSame(0.0, $bbox['east']);
    }

    // ----------------------------------------------------------------
    //  centroid
    // ----------------------------------------------------------------

    public function test_centroid_returns_average_of_vertices(): void
    {
        $polygon = [
            ['lat' => -23.50, 'lng' => -46.60],
            ['lat' => -23.52, 'lng' => -46.62],
            ['lat' => -23.54, 'lng' => -46.64],
        ];

        $centroid = $this->calculator->centroid($polygon);

        $this->assertEqualsWithDelta(-23.52, $centroid['lat'], 0.001);
        $this->assertEqualsWithDelta(-46.62, $centroid['lng'], 0.001);
    }

    // ----------------------------------------------------------------
    //  bufferBoundingBox
    // ----------------------------------------------------------------

    public function test_buffer_bounding_box_expands_equally(): void
    {
        $center = ['lat' => -23.50, 'lng' => -46.60];
        $radius = 100.0; // 100m

        $bbox = $this->calculator->bufferBoundingBox($center, $radius);

        // O bbox deve estar ~100m em cada direção
        $this->assertLessThan($center['lat'], $bbox['south']);
        $this->assertGreaterThan($center['lat'], $bbox['north']);
        $this->assertLessThan($center['lng'], $bbox['west']);
        $this->assertGreaterThan($center['lng'], $bbox['east']);

        // A diferença deve ser ~0.0009 graus (~100m)
        $deltaLat = $bbox['north'] - $bbox['south'];
        $this->assertGreaterThan(0.001, $deltaLat);
        $this->assertLessThan(0.002, $deltaLat);
    }
}
