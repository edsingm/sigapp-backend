<?php

namespace Tests\Feature\Api;

use App\Models\Central\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPlansApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_plans_endpoint_returns_active_plans(): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\EntitlementSeeder::class);

        $response = $this
            ->withHeader('Host', 'localhost')
            ->getJson('/api/v1/plans');

        $response->assertOk()->assertJsonPath('success', true);

        $plans = $response->json('data');
        $this->assertNotEmpty($plans);
        $this->assertArrayHasKey('features', $plans[0]);
        $this->assertArrayHasKey('limits', $plans[0]);
    }

    public function test_public_plans_endpoint_does_not_expose_stripe_price_id(): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\EntitlementSeeder::class);

        $response = $this
            ->withHeader('Host', 'localhost')
            ->getJson('/api/v1/plans');

        $response->assertOk();

        $plan = $response->json('data.0');
        $this->assertArrayNotHasKey('stripe_price_id', $plan);
        $this->assertArrayNotHasKey('entitlements', $plan);
    }
}
