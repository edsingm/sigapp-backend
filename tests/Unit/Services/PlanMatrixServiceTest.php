<?php

namespace Tests\Unit\Services;

use App\Models\Central\Plan;
use App\Services\PlanMatrixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanMatrixServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\EntitlementSeeder::class);
    }

    public function test_it_resolves_features_from_db_plan(): void
    {
        $service = app(PlanMatrixService::class);
        $basico  = Plan::where('slug', 'basico')->firstOrFail();

        $this->assertTrue($service->hasFeature($basico, 'dashboard.enabled'));
        $this->assertFalse($service->hasFeature($basico, 'committee'));
    }

    public function test_it_resolves_limits_from_db_plan(): void
    {
        $service = app(PlanMatrixService::class);
        $basico  = Plan::where('slug', 'basico')->firstOrFail();
        $pro     = Plan::where('slug', 'pro')->firstOrFail();

        $this->assertSame(3, $service->getLimit($basico, 'users'));
        $this->assertSame(-1, $service->getLimit($pro, 'users'));
        $this->assertTrue($service->isUnlimitedLimit($pro, 'users'));
    }

    public function test_it_returns_empty_matrix_for_plan_without_entitlements(): void
    {
        $service = app(PlanMatrixService::class);
        $plan    = Plan::create(['name' => 'Empty', 'slug' => 'empty', 'price' => 0, 'sort_order' => 9, 'is_active' => true, 'trial_days' => 0]);

        $matrix = $service->resolve($plan);

        $this->assertSame([], $matrix['features']);
        $this->assertSame([], $matrix['limits']);
    }

    public function test_it_throws_for_unknown_slug(): void
    {
        $service = app(PlanMatrixService::class);

        $this->expectException(\InvalidArgumentException::class);

        $service->resolve('nonexistent');
    }
}
