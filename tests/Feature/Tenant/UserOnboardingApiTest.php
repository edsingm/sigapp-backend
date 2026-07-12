<?php

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserOnboardingApiTest extends TestCase
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
            CheckFeature::class,
        ]);
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
        $this->user = User::create([
            'name' => 'Onboarding User',
            'email' => 'onboarding-user@test.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_onboarding_events_are_allowlisted_and_idempotent(): void
    {
        $this->actingAs($this->user)->getJson('/api/v1/me/onboarding')
            ->assertOk()
            ->assertJsonPath('data.progress', 0);

        $eventId = (string) Str::uuid();
        $payload = [
            'event_id' => $eventId,
            'event' => 'dashboard_viewed',
            'metadata' => ['source' => 'test'],
        ];
        $this->actingAs($this->user)->postJson('/api/v1/me/onboarding/events', $payload)
            ->assertOk()->assertJsonPath('data.completed_steps.0', 'dashboard');
        $this->actingAs($this->user)->postJson('/api/v1/me/onboarding/events', $payload)
            ->assertOk();

        $this->assertDatabaseCount('user_onboarding_events', 1);
        $this->actingAs($this->user)->postJson('/api/v1/me/onboarding/dismiss')->assertOk()
            ->assertJsonPath('data.dismissed', true);
        $this->actingAs($this->user)->postJson('/api/v1/me/onboarding/resume')->assertOk()
            ->assertJsonPath('data.dismissed', false);
    }

    public function test_onboarding_rejects_unknown_event(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/me/onboarding/events', [
            'event_id' => (string) Str::uuid(),
            'event' => 'free_form_metric',
        ])->assertUnprocessable()->assertJsonValidationErrors(['event']);
    }
}
