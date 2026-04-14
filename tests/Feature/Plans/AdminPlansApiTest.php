<?php

namespace Tests\Feature\Plans;

use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use App\Models\User;
use Database\Seeders\EntitlementSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPlansApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@sigapp.test',
            'password' => 'password',
            'is_admin' => true,
        ]);

        Sanctum::actingAs($user, ['admin']);
    }

    private function adminJson(string $method, string $uri, array $data = []): TestResponse
    {
        return $this
            ->withHeader('Host', 'localhost')
            ->{$method.'Json'}($uri, $data);
    }

    // ─── Auth Guard Tests ─────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->withHeader('Host', 'localhost')->getJson('/api/v1/admin/plans');

        $response->assertUnauthorized();
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = User::create([
            'name' => 'Guest',
            'email' => 'guest@sigapp.test',
            'password' => 'password',
            'is_admin' => false,
        ]);
        Sanctum::actingAs($user, ['admin']);

        $response = $this->withHeader('Host', 'localhost')->getJson('/api/v1/admin/plans');

        $response->assertForbidden();
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_it_lists_all_plans(): void
    {
        $this->seed(PlanSeeder::class);
        $this->actingAsAdmin();

        $response = $this->adminJson('get', '/api/v1/admin/plans');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data'));
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_it_shows_a_single_plan_with_entitlements(): void
    {
        $this->seed(PlanSeeder::class);
        $this->seed(EntitlementSeeder::class);
        $this->actingAsAdmin();

        $plan = Plan::where('slug', 'pro')->firstOrFail();
        $response = $this->adminJson('get', "/api/v1/admin/plans/{$plan->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id', 'slug', 'name', 'features', 'limits', 'entitlements']]);
    }

    public function test_show_returns_404_for_missing_plan(): void
    {
        $this->actingAsAdmin();

        $response = $this->adminJson('get', '/api/v1/admin/plans/99999');

        $response->assertNotFound();
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_it_creates_a_plan(): void
    {
        $this->actingAsAdmin();

        $response = $this->adminJson('post', '/api/v1/admin/plans', [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price' => 999.00,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('plans', ['slug' => 'enterprise']);
    }

    public function test_create_fails_with_duplicate_slug(): void
    {
        $this->seed(PlanSeeder::class);
        $this->actingAsAdmin();

        $response = $this->adminJson('post', '/api/v1/admin/plans', [
            'name' => 'Duplicate',
            'slug' => 'pro',
            'price' => 0,
            'trial_days' => 0,
        ]);

        $response->assertUnprocessable();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_it_updates_a_plan(): void
    {
        $this->seed(PlanSeeder::class);
        $this->actingAsAdmin();

        $plan = Plan::where('slug', 'basico')->firstOrFail();
        $response = $this->adminJson('put', "/api/v1/admin/plans/{$plan->id}", [
            'name' => 'Básico Atualizado',
            'slug' => 'basico',
            'price' => 319.00,
            'trial_days' => 7,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'name' => 'Básico Atualizado']);
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_it_deletes_a_plan(): void
    {
        $this->actingAsAdmin();

        $plan = Plan::create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'price' => 0,
            'trial_days' => 0,
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $response = $this->adminJson('delete', "/api/v1/admin/plans/{$plan->id}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    // ─── Sync Entitlements ────────────────────────────────────────────────────

    public function test_it_syncs_entitlements_for_a_plan(): void
    {
        $this->actingAsAdmin();

        $plan = Plan::create(['name' => 'Sync Test', 'slug' => 'sync-test', 'price' => 0, 'trial_days' => 0, 'is_active' => true, 'sort_order' => 1]);
        $entA = Entitlement::create(['key' => 'f.a', 'type' => 'feature', 'label' => 'A', 'default_value' => false]);
        $entB = Entitlement::create(['key' => 'f.b', 'type' => 'feature', 'label' => 'B', 'default_value' => false]);

        $response = $this->adminJson('put', "/api/v1/admin/plans/{$plan->id}/entitlements", [
            'entitlements' => [
                ['entitlement_id' => $entA->id, 'value' => true],
                ['entitlement_id' => $entB->id, 'value' => false],
            ],
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(2, $plan->fresh()->entitlements);
    }

    // ─── Response does not expose sensitive fields ────────────────────────────

    public function test_plan_response_does_not_expose_stripe_price_id(): void
    {
        $this->seed(PlanSeeder::class);
        $this->actingAsAdmin();

        $plan = Plan::where('slug', 'pro')->firstOrFail();
        $response = $this->adminJson('get', "/api/v1/admin/plans/{$plan->id}");

        $data = $response->json('data');
        $this->assertArrayNotHasKey('stripe_price_id', $data);
    }
}
