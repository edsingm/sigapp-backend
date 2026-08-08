<?php

namespace Tests\Feature\Plans;

use App\Models\User;
use Database\Seeders\EntitlementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBillingAddonsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EntitlementSeeder::class);

        $admin = User::factory()->admin()->createOne();
        $admin->forceFill(['admin_mfa_confirmed_at' => now()])->save();
        Sanctum::actingAs($admin, ['admin', 'admin:mfa']);
    }

    public function test_admin_can_create_and_read_an_addon_with_its_stripe_price(): void
    {
        $response = $this->withHeader('Host', 'localhost')->postJson('/api/v1/admin/billing-addons', [
            'slug' => 'storage-10gb-test',
            'name' => 'Storage 10 GB',
            'type' => 'limit_pack',
            'stripe_price_id' => 'price_storage10gbtest',
            'definition' => [
                'grants' => [
                    ['key' => 'storage_gb', 'type' => 'limit', 'unit_value' => 10],
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'storage-10gb-test')
            ->assertJsonPath('data.stripe_price_id', 'price_storage10gbtest');
    }

    public function test_admin_cannot_create_a_limit_pack_with_a_feature_grant(): void
    {
        $response = $this->withHeader('Host', 'localhost')->postJson('/api/v1/admin/billing-addons', [
            'slug' => 'invalid-limit-pack',
            'name' => 'Invalid',
            'type' => 'limit_pack',
            'stripe_price_id' => 'price_invalidlimitpack',
            'definition' => [
                'grants' => [
                    ['key' => 'reports.builder', 'type' => 'feature', 'unit_value' => true],
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_BILLING_ADDON');
    }
}
