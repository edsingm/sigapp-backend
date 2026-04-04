<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\PlanResource;
use App\Models\Central\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_serializes_plan_fields(): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\EntitlementSeeder::class);

        $plan = Plan::where('slug', 'pro')->first();

        $payload = (new PlanResource($plan))->resolve();

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('name', $payload);
        $this->assertArrayHasKey('slug', $payload);
        $this->assertArrayHasKey('features', $payload);
        $this->assertArrayHasKey('limits', $payload);
        $this->assertSame(-1, $payload['limits']['users']);
        $this->assertTrue($payload['features']['exports']['pdf']);
    }

    public function test_resource_does_not_expose_stripe_or_internal_fields(): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\EntitlementSeeder::class);

        $plan    = Plan::where('slug', 'pro')->first();
        $payload = (new PlanResource($plan))->resolve();

        $this->assertArrayNotHasKey('stripe_price_id', $payload);
        $this->assertArrayNotHasKey('max_users', $payload);
        $this->assertArrayNotHasKey('max_terrenos', $payload);
        $this->assertArrayNotHasKey('feature_flags', $payload);
    }
}
