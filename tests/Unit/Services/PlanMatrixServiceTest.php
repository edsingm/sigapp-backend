<?php

namespace Tests\Unit\Services;

use App\Models\Central\Plan;
use App\Services\PlanMatrixService;
use InvalidArgumentException;
use Tests\TestCase;

class PlanMatrixServiceTest extends TestCase
{
    public function test_it_resolves_normalized_features_and_limits_for_each_plan(): void
    {
        $service = app(PlanMatrixService::class);

        $broker = new Plan(['slug' => 'broker']);
        $basico = new Plan(['slug' => 'basico']);
        $master = new Plan(['slug' => 'master']);
        $pro = new Plan(['slug' => 'pro']);

        $this->assertFalse($service->hasFeature($broker, 'dashboard.enabled'));
        $this->assertTrue($service->hasFeature($basico, 'dashboard.overview'));
        $this->assertTrue($service->hasFeature($master, 'dashboard.funnel'));
        $this->assertTrue($service->hasFeature($pro, 'committee'));

        $this->assertFalse($service->hasFeature($broker, 'exports.pdf'));
        $this->assertTrue($service->hasFeature($basico, 'exports.pdf'));
        $this->assertFalse($service->hasFeature($master, 'committee'));
        $this->assertTrue($service->hasFeature($pro, 'viabilities.kpis'));

        $this->assertSame(1, $service->getLimit($broker, 'users'));
        $this->assertSame(2, $service->getLimit($basico, 'products'));
        $this->assertSame(3, $service->getLimit($master, 'storage_gb'));
        $this->assertSame(-1, $service->getLimit($pro, 'terrenos'));
        $this->assertTrue($service->isUnlimitedLimit($pro, 'products'));
    }

    public function test_it_validates_configured_plan_slugs(): void
    {
        $service = app(PlanMatrixService::class);

        $service->assertConfiguredSlugs(['broker', 'basico', 'master', 'pro']);

        $this->expectException(InvalidArgumentException::class);

        $service->assertConfiguredSlugs(['legacy']);
    }
}
