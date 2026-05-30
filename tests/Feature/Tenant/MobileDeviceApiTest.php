<?php

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Models\Tenant\MobileDeviceInstallation;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileDeviceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            InitializeTenancyFlexible::class,
            AddTenantContextToLogs::class,
            ApiRequestLogger::class,
            CheckSubscriptionStatus::class,
            EnsureTenantContext::class,
            EnsureTenantUser::class,
            EnsureTenantAdmin::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        $this->user = User::create([
            'name' => 'Mobile User',
            'email' => 'mobile-user@test.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_authenticated_user_can_register_mobile_device(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/mobile/devices', [
            'installation_id' => 'device-installation-1',
            'expo_push_token' => 'ExponentPushToken[test]',
            'device_name' => 'iPhone Test',
            'app_version' => '1.0.0',
            'platform' => 'ios',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.installation_id', 'device-installation-1')
            ->assertJsonPath('data.platform', 'ios');

        $this->assertDatabaseHas('mobile_device_installations', [
            'user_id' => $this->user->id,
            'installation_id' => 'device-installation-1',
            'platform' => 'ios',
        ]);
    }

    public function test_register_mobile_device_requires_valid_payload(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/mobile/devices', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['installation_id', 'platform']);
    }

    public function test_user_can_unregister_own_mobile_device(): void
    {
        MobileDeviceInstallation::create([
            'user_id' => $this->user->id,
            'installation_id' => 'device-installation-2',
            'platform' => 'android',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/v1/mobile/devices/device-installation-2');

        $response->assertNoContent();

        $this->assertDatabaseMissing('mobile_device_installations', [
            'user_id' => $this->user->id,
            'installation_id' => 'device-installation-2',
        ]);
    }
}