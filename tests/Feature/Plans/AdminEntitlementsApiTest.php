<?php

namespace Tests\Feature\Plans;

use App\Models\Central\Entitlement;
use App\Models\User;
use Database\Seeders\EntitlementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEntitlementsApiTest extends TestCase
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

    // ─── Auth Guard ───────────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->withHeader('Host', 'localhost')->getJson('/api/v1/admin/entitlements');

        $response->assertUnauthorized();
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_it_lists_all_entitlements(): void
    {
        $this->seed(EntitlementSeeder::class);
        $this->actingAsAdmin();

        $response = $this->adminJson('get', '/api/v1/admin/entitlements');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('data'));
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_it_shows_a_single_entitlement(): void
    {
        $this->actingAsAdmin();

        $ent = Entitlement::create([
            'key' => 'show.me',
            'type' => 'feature',
            'label' => 'Show Me',
            'default_value' => false,
        ]);

        $response = $this->adminJson('get', "/api/v1/admin/entitlements/{$ent->id}");

        $response->assertOk()
            ->assertJsonPath('data.key', 'show.me')
            ->assertJsonPath('data.label', 'Show Me');
    }

    public function test_show_returns_404_for_missing_entitlement(): void
    {
        $this->actingAsAdmin();

        $response = $this->adminJson('get', '/api/v1/admin/entitlements/99999');

        $response->assertNotFound();
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_it_creates_an_entitlement(): void
    {
        $this->actingAsAdmin();

        $response = $this->adminJson('post', '/api/v1/admin/entitlements', [
            'key' => 'new.feat',
            'type' => 'feature',
            'label' => 'New Feature',
            'default_value' => false,
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertDatabaseHas('entitlements', ['key' => 'new.feat']);
    }

    public function test_create_fails_with_duplicate_key(): void
    {
        $this->actingAsAdmin();

        Entitlement::create(['key' => 'dup.key', 'type' => 'feature', 'label' => 'Dup', 'default_value' => false]);

        $response = $this->adminJson('post', '/api/v1/admin/entitlements', [
            'key' => 'dup.key',
            'type' => 'feature',
            'label' => 'Dup 2',
            'default_value' => false,
        ]);

        // 'unique:entitlements,key' validation catches the duplicate before the service does
        $response->assertUnprocessable();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_it_updates_an_entitlement(): void
    {
        $this->actingAsAdmin();

        $ent = Entitlement::create(['key' => 'upd.feat', 'type' => 'feature', 'label' => 'Old Label', 'default_value' => false]);

        $response = $this->adminJson('put', "/api/v1/admin/entitlements/{$ent->id}", [
            'label' => 'New Label',
        ]);

        $response->assertOk()->assertJsonPath('data.label', 'New Label');
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_it_deletes_an_entitlement(): void
    {
        $this->actingAsAdmin();

        $ent = Entitlement::create(['key' => 'del.feat', 'type' => 'feature', 'label' => 'Del', 'default_value' => false]);

        $response = $this->adminJson('delete', "/api/v1/admin/entitlements/{$ent->id}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('entitlements', ['id' => $ent->id]);
    }

    // ─── Response does not expose sensitive fields ────────────────────────────

    public function test_entitlement_response_does_not_expose_plans_relation(): void
    {
        $this->actingAsAdmin();

        $ent = Entitlement::create(['key' => 'safe.feat', 'type' => 'feature', 'label' => 'Safe', 'default_value' => false]);

        $response = $this->adminJson('get', "/api/v1/admin/entitlements/{$ent->id}");

        $data = $response->json('data');
        $this->assertArrayNotHasKey('plans', $data);
        $this->assertArrayNotHasKey('pivot', $data);
    }
}
