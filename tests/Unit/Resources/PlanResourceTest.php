<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\PlanResource;
use App\Models\Central\Plan;
use Tests\TestCase;

class PlanResourceTest extends TestCase
{
    public function test_it_serializes_only_the_new_plan_contract(): void
    {
        $plan = new Plan([
            'id' => 1,
            'name' => 'SIG Pro',
            'slug' => 'pro',
            'description' => 'Plano completo',
            'price' => 94700,
            'trial_days' => 7,
            'is_active' => true,
            'is_popular' => true,
        ]);

        $payload = (new PlanResource($plan))->resolve();

        $this->assertArrayHasKey('features', $payload);
        $this->assertArrayHasKey('limits', $payload);
        $this->assertSame(-1, $payload['limits']['users']);
        $this->assertTrue($payload['features']['exports']['pdf']);

        $this->assertArrayNotHasKey('entitlements', $payload);
        $this->assertArrayNotHasKey('feature_flags', $payload);
        $this->assertArrayNotHasKey('max_users', $payload);
        $this->assertArrayNotHasKey('max_terrenos', $payload);
        $this->assertArrayNotHasKey('max_storage_gb', $payload);
    }
}
