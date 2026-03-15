<?php

namespace Tests\Feature\Api;

use App\Models\Central\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPlansApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_plans_endpoint_returns_only_features_and_limits(): void
    {
        Plan::create([
            'name' => 'SIG Básico',
            'slug' => 'basico',
            'description' => 'Plano básico',
            'price' => 29700,
            'trial_days' => 7,
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
        ]);

        $response = $this
            ->withHeader('Host', 'localhost')
            ->getJson('/api/v1/plans');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.slug', 'basico')
            ->assertJsonPath('data.0.features.dashboard.enabled', true)
            ->assertJsonPath('data.0.limits.products', 2);

        $plan = $response->json('data.0');

        $this->assertIsArray($plan);
        $this->assertArrayNotHasKey('entitlements', $plan);
        $this->assertArrayNotHasKey('feature_flags', $plan);
        $this->assertArrayNotHasKey('max_users', $plan);
        $this->assertArrayNotHasKey('max_terrenos', $plan);
        $this->assertArrayNotHasKey('max_storage_gb', $plan);
    }
}
