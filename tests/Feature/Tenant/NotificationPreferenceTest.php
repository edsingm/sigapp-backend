<?php

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\MobileDeviceInstallation;
use App\Models\Tenant\NotificationPreference;
use App\Models\Tenant\User;
use App\Notifications\EmailDigestNotification;
use App\Notifications\Workflow\ViabilidadeDecidedNotification;
use App\Services\Tenant\MobilePushService;
use App\Services\Tenant\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
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
            'name' => 'Pref User',
            'email' => 'pref-user@test.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_index_returns_full_catalog_with_defaults_enabled(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/me/notification-preferences');

        $response->assertOk()
            ->assertJsonPath('data.categories.0.key', 'viabilidade.submetida')
            ->assertJsonPath('data.categories.0.channels.0.enabled', true);

        // contrato.assinado expõe os três canais (e-mail, in-app e push).
        $contrato = collect($response->json('data.categories'))->firstWhere('key', 'contrato.assinado');
        $this->assertEqualsCanonicalizing(
            ['email', 'in_app', 'push'],
            array_column($contrato['channels'], 'channel'),
        );
    }

    public function test_update_persists_preferences(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/v1/me/notification-preferences', [
            'preferences' => [
                ['category' => 'projeto.finalizado', 'channel' => 'push', 'enabled' => false],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->user->id,
            'category' => 'projeto.finalizado',
            'channel' => 'push',
            'enabled' => false,
        ]);
    }

    public function test_service_ignores_unavailable_category_or_channel(): void
    {
        $service = app(NotificationPreferenceService::class);

        $service->updateForUser($this->user, [
            ['category' => 'categoria.inexistente', 'channel' => 'email', 'enabled' => false],
            ['category' => 'projeto.finalizado', 'channel' => 'canal-invalido', 'enabled' => false],
        ]);

        $this->assertSame(
            0,
            NotificationPreference::where('user_id', $this->user->id)->count(),
        );
    }

    public function test_update_rejects_invalid_category(): void
    {
        $this->actingAs($this->user)->putJson('/api/v1/me/notification-preferences', [
            'preferences' => [
                ['category' => 'inexistente', 'channel' => 'email', 'enabled' => false],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['preferences.0.category']);
    }

    public function test_service_defaults_to_enabled_and_respects_override(): void
    {
        $service = app(NotificationPreferenceService::class);

        $this->assertTrue($service->isEnabled($this->user, 'projeto.finalizado', 'push'));
        $this->assertFalse($service->isEnabled($this->user, 'projeto.finalizado', 'canal-invalido'));

        NotificationPreference::create([
            'user_id' => $this->user->id,
            'category' => 'projeto.finalizado',
            'channel' => 'push',
            'enabled' => false,
        ]);

        $service = app(NotificationPreferenceService::class); // novo cache
        $this->assertFalse($service->isEnabled($this->user, 'projeto.finalizado', 'push'));
    }

    public function test_push_disabled_skips_expo_but_keeps_in_app(): void
    {
        Http::fake();

        MobileDeviceInstallation::create([
            'user_id' => $this->user->id,
            'installation_id' => 'dev-1',
            'platform' => 'ios',
            'expo_push_token' => 'ExponentPushToken[abc]',
        ]);

        NotificationPreference::create([
            'user_id' => $this->user->id,
            'category' => 'projeto.finalizado',
            'channel' => 'push',
            'enabled' => false,
        ]);

        app(MobilePushService::class)->notifyUsers([$this->user], $this->payload());

        $this->assertDatabaseHas('mobile_notifications', [
            'user_id' => $this->user->id,
            'type' => 'projeto.finalizado',
        ]);
        Http::assertNothingSent();
    }

    public function test_in_app_disabled_skips_persistence_but_sends_push(): void
    {
        Http::fake();

        MobileDeviceInstallation::create([
            'user_id' => $this->user->id,
            'installation_id' => 'dev-1',
            'platform' => 'ios',
            'expo_push_token' => 'ExponentPushToken[abc]',
        ]);

        NotificationPreference::create([
            'user_id' => $this->user->id,
            'category' => 'projeto.finalizado',
            'channel' => 'in_app',
            'enabled' => false,
        ]);

        app(MobilePushService::class)->notifyUsers([$this->user], $this->payload());

        $this->assertDatabaseMissing('mobile_notifications', [
            'user_id' => $this->user->id,
            'type' => 'projeto.finalizado',
        ]);
        Http::assertSentCount(1);
    }

    public function test_all_channels_disabled_does_nothing(): void
    {
        Http::fake();

        foreach (['in_app', 'push', 'email'] as $channel) {
            NotificationPreference::create([
                'user_id' => $this->user->id,
                'category' => 'projeto.finalizado',
                'channel' => $channel,
                'enabled' => false,
            ]);
        }

        $result = app(MobilePushService::class)->notifyUsers([$this->user], $this->payload());

        $this->assertTrue($result->isEmpty());
        $this->assertDatabaseMissing('mobile_notifications', ['user_id' => $this->user->id]);
        Http::assertNothingSent();
    }

    public function test_workflow_notification_respects_email_preference(): void
    {
        $notification = new ViabilidadeDecidedNotification('Terreno X', 'aprovada', 1);

        // Default habilitado.
        $this->assertSame(['mail'], $notification->via($this->user));

        NotificationPreference::create([
            'user_id' => $this->user->id,
            'category' => 'viabilidade.decidida',
            'channel' => 'email',
            'enabled' => false,
        ]);

        $this->assertSame([], $notification->via($this->freshUser()));

        // Destinatário que não é usuário do tenant recebe normalmente.
        $this->assertSame(['mail'], $notification->via(new \stdClass));
    }

    public function test_quiet_hours_detection_handles_midnight_crossover(): void
    {
        $service = app(NotificationPreferenceService::class);

        $this->user->forceFill(['quiet_hours_start' => '22:00', 'quiet_hours_end' => '07:00'])->save();
        $this->user->refresh();

        $this->assertTrue($service->isWithinQuietHours($this->user, Carbon::parse('2026-06-26 23:30')));
        $this->assertTrue($service->isWithinQuietHours($this->user, Carbon::parse('2026-06-26 03:00')));
        $this->assertFalse($service->isWithinQuietHours($this->user, Carbon::parse('2026-06-26 12:00')));
    }

    public function test_push_suppressed_during_quiet_hours_but_inbox_kept(): void
    {
        Http::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-26 23:30'));

        MobileDeviceInstallation::create([
            'user_id' => $this->user->id,
            'installation_id' => 'dev-1',
            'platform' => 'ios',
            'expo_push_token' => 'ExponentPushToken[abc]',
        ]);

        $this->user->forceFill(['quiet_hours_start' => '22:00', 'quiet_hours_end' => '07:00'])->save();

        app(MobilePushService::class)->notifyUsers([$this->user->fresh()], $this->payload());

        $this->assertDatabaseHas('mobile_notifications', [
            'user_id' => $this->user->id,
            'type' => 'projeto.finalizado',
        ]);
        Http::assertNothingSent();

        Carbon::setTestNow();
    }

    public function test_digest_suppresses_immediate_email_only_when_in_inbox(): void
    {
        $notification = new ViabilidadeDecidedNotification('Terreno X', 'aprovada', 1);

        // Instant (default): e-mail imediato.
        $this->assertSame(['mail'], $notification->via($this->user));

        // Digest ligado + categoria no inbox (in-app on) → suprime imediato (vai pro resumo).
        $this->user->forceFill(['email_digest_frequency' => 'daily'])->save();
        $this->assertSame([], $notification->via($this->freshUser()));

        // Digest ligado mas in-app off para a categoria → mantém e-mail imediato (sem perda).
        NotificationPreference::create([
            'user_id' => $this->user->id,
            'category' => 'viabilidade.decidida',
            'channel' => 'in_app',
            'enabled' => false,
        ]);
        $this->assertSame(['mail'], $notification->via($this->freshUser()));
    }

    public function test_settings_endpoint_returns_and_updates(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/me/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.settings.email_digest_frequency', 'instant')
            ->assertJsonPath('data.settings.quiet_hours_start', null);

        $this->actingAs($this->user)
            ->putJson('/api/v1/me/notification-settings', [
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
                'email_digest_frequency' => 'weekly',
            ])
            ->assertOk()
            ->assertJsonPath('data.settings.email_digest_frequency', 'weekly')
            ->assertJsonPath('data.settings.quiet_hours_start', '22:00');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email_digest_frequency' => 'weekly',
            'quiet_hours_start' => '22:00',
        ]);
    }

    public function test_settings_endpoint_validates_input(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/v1/me/notification-settings', [
                'quiet_hours_start' => '25:99',
                'email_digest_frequency' => 'mensal',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quiet_hours_start', 'email_digest_frequency']);
    }

    public function test_email_digest_notification_renders(): void
    {
        $notification = new EmailDigestNotification(
            [['title' => 'Projeto finalizado', 'body' => 'Detalhe', 'target_route' => '/x', 'created_at' => null]],
            'daily',
        );

        $mail = $notification->toMail($this->user);

        $this->assertStringContainsString('Resumo de notificações', $mail->subject);
    }

    private function freshUser(): User
    {
        $user = $this->user->fresh();

        if ($user === null) {
            $this->fail('Usuário não encontrado após refresh.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'title' => 'Projeto finalizado',
            'body' => 'O projeto foi finalizado.',
            'type' => 'projeto.finalizado',
            'category' => 'projeto.finalizado',
            'target_route' => '/projetos/1',
        ];
    }
}
