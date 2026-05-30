<?php

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureCentralContext;
use App\Http\Middleware\EnsureCentralUser;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LocaleApiTest extends TestCase
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
            EnsureCentralContext::class,
            EnsureCentralUser::class,
            EnsureUserIsAdmin::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        $this->user = User::create([
            'name' => 'Locale User',
            'email' => 'locale-user@test.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_authenticated_user_can_update_locale(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/v1/locale', [
            'locale' => 'en-us',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.locale', 'en-us');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'locale' => 'en-us',
        ]);
    }

    public function test_locale_update_validates_supported_locale(): void
    {
        $this->actingAs($this->user)->putJson('/api/v1/locale', [
            'locale' => 'es-es',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);
    }
}
