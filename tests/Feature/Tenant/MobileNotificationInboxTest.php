<?php

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\MobileNotification;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileNotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $other;

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
            'name' => 'Inbox User',
            'email' => 'inbox-user@test.com',
            'password' => Hash::make('password123'),
        ]);

        $this->other = User::create([
            'name' => 'Other User',
            'email' => 'other-user@test.com',
            'password' => Hash::make('password123'),
        ]);
    }

    private function makeNotification(User $user, ?string $readAt = null): MobileNotification
    {
        return MobileNotification::create([
            'user_id' => $user->id,
            'title' => 'Teste',
            'body' => 'Corpo',
            'type' => 'projeto.finalizado',
            'sent_at' => now(),
            'read_at' => $readAt,
        ]);
    }

    public function test_unread_count_counts_only_unread_of_user(): void
    {
        $this->makeNotification($this->user);
        $this->makeNotification($this->user);
        $this->makeNotification($this->user, now()->toDateTimeString());
        $this->makeNotification($this->other);

        $this->actingAs($this->user)
            ->getJson('/api/v1/mobile/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_read_all_marks_only_user_notifications(): void
    {
        $this->makeNotification($this->user);
        $this->makeNotification($this->user);
        $otherUnread = $this->makeNotification($this->other);

        $this->actingAs($this->user)
            ->postJson('/api/v1/mobile/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked', 2);

        $this->assertSame(0, MobileNotification::where('user_id', $this->user->id)->whereNull('read_at')->count());

        $refreshed = $otherUnread->fresh();
        if ($refreshed === null) {
            $this->fail('Notificação não encontrada após refresh.');
        }
        $this->assertNull($refreshed->read_at);
    }

    public function test_user_can_delete_own_notification(): void
    {
        $notification = $this->makeNotification($this->user);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/mobile/notifications/{$notification->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('mobile_notifications', ['id' => $notification->id]);
    }

    public function test_user_cannot_delete_other_users_notification(): void
    {
        $notification = $this->makeNotification($this->other);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/mobile/notifications/{$notification->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('mobile_notifications', ['id' => $notification->id]);
    }
}
