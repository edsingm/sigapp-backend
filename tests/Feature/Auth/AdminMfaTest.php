<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Events\AdminMfaChanged;
use App\Models\Central\AdminMfaRecoveryCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use OTPHP\TOTP;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AdminMfaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([AdminMfaChanged::class]);
    }

    public function test_admin_login_requires_mfa_setup_before_issuing_token(): void
    {
        $admin = $this->createAdmin();

        $login = $this->adminJson('post', '/api/v1/admin/login', [
            'email' => $admin->email,
            'password' => 'password123',
            'device_name' => 'browser-test',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.state', 'MFA_SETUP_REQUIRED')
            ->assertJsonMissingPath('data.token')
            ->assertJsonStructure([
                'data' => [
                    'challenge',
                    'expires_at',
                    'setup' => ['manual_key', 'otpauth_uri'],
                ],
            ]);
        self::assertStringContainsString('no-store', (string) $login->headers->get('Cache-Control'));

        $manualKey = (string) $login->json('data.setup.manual_key');
        $verification = $this->adminJson('post', '/api/v1/admin/login/verify', [
            'challenge' => $login->json('data.challenge'),
            'code' => $this->totpCode($manualKey),
        ]);

        $verification->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(10, 'data.recovery_codes')
            ->assertJsonMissingPath('data.user.admin_mfa_secret');
        self::assertStringContainsString('no-store', (string) $verification->headers->get('Cache-Control'));

        $token = (string) $verification->json('data.token');
        self::assertNotSame('', $token);
        $admin->refresh();
        self::assertNotNull($admin->admin_mfa_confirmed_at);
        self::assertArrayNotHasKey('admin_mfa_secret', $admin->toArray());
        self::assertSame(10, AdminMfaRecoveryCode::query()->where('user_id', $admin->id)->whereNull('used_at')->count());

        $this->withAdminToken($token)
            ->getJson('/api/v1/admin/mfa')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.recovery_codes_remaining', 10);

        $this->withAdminToken($token)
            ->postJson('/api/v1/auth/refresh')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ADMIN_MFA_REAUTH_REQUIRED');
    }

    public function test_mfa_challenge_is_single_use_and_recovery_code_is_consumed(): void
    {
        [$admin, $secret, $recoveryCodes] = $this->completeSetup();

        $login = $this->adminJson('post', '/api/v1/admin/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $verification = $this->adminJson('post', '/api/v1/admin/login/verify', [
            'challenge' => $login->json('data.challenge'),
            'recovery_code' => $recoveryCodes[0],
        ]);
        $verification->assertOk();

        $this->adminJson('post', '/api/v1/admin/login/verify', [
            'challenge' => $login->json('data.challenge'),
            'code' => $this->totpCode($secret),
        ])->assertUnauthorized()
            ->assertJsonPath('error.code', 'MFA_CHALLENGE_INVALID');

        $admin->refresh();
        self::assertSame(9, AdminMfaRecoveryCode::query()->where('user_id', $admin->id)->whereNull('used_at')->count());
    }

    public function test_rotation_requires_second_verification_and_revokes_previous_tokens(): void
    {
        [$admin, $secret] = $this->completeSetup();
        $this->travel(31)->seconds();
        $login = $this->adminJson('post', '/api/v1/admin/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);
        $token = (string) $this->adminJson('post', '/api/v1/admin/login/verify', [
            'challenge' => $login->json('data.challenge'),
            'code' => $this->totpCode($secret),
        ])->json('data.token');

        $this->travel(31)->seconds();
        $rotation = $this->withToken($token)->postJson('/api/v1/admin/mfa/rotate', [
            'password' => 'password123',
            'code' => $this->totpCode($secret),
        ]);
        $rotation->assertOk()->assertJsonPath('success', true);
        self::assertStringContainsString('no-store', (string) $rotation->headers->get('Cache-Control'));

        $newSecret = (string) $rotation->json('data.setup.manual_key');
        $rotated = $this->adminJson('post', '/api/v1/admin/mfa/rotate/verify', [
            'challenge' => $rotation->json('data.challenge'),
            'code' => $this->totpCode($newSecret),
        ], $token);
        $rotated->assertOk()->assertJsonCount(10, 'data.recovery_codes');
        self::assertStringContainsString('no-store', (string) $rotated->headers->get('Cache-Control'));

        self::assertGreaterThanOrEqual(1, PersonalAccessToken::query()->where('tokenable_id', $admin->id)->count());
        [$oldTokenId] = explode('|', $token, 2);
        self::assertDatabaseMissing('personal_access_tokens', ['id' => $oldTokenId]);

        $this->app['auth']->guard('sanctum')->forgetUser();
        $this->withAdminToken($token)->getJson('/api/v1/admin/mfa')->assertUnauthorized();
    }

    /** @return array{0: User, 1: string, 2: list<string>} */
    private function completeSetup(): array
    {
        $admin = $this->createAdmin();
        $login = $this->adminJson('post', '/api/v1/admin/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);
        $secret = (string) $login->json('data.setup.manual_key');
        $verification = $this->adminJson('post', '/api/v1/admin/login/verify', [
            'challenge' => $login->json('data.challenge'),
            'code' => $this->totpCode($secret),
        ]);

        $freshAdmin = $admin->fresh();
        if (! $freshAdmin instanceof User) {
            self::fail('Admin was not found after MFA setup.');
        }

        $rawCodes = $verification->json('data.recovery_codes');
        if (! is_array($rawCodes)) {
            self::fail('MFA setup did not return recovery codes.');
        }

        $codes = array_values(array_map(static fn (mixed $code): string => (string) $code, $rawCodes));

        return [$freshAdmin, $secret, $codes];
    }

    private function createAdmin(): User
    {
        return User::factory()->admin()->createOne([
            'password' => Hash::make('password123'),
        ]);
    }

    private function totpCode(string $secret): string
    {
        if ($secret === '') {
            self::fail('TOTP secret cannot be empty.');
        }

        $totp = TOTP::createFromSecret($secret);
        $totp->setPeriod(30);
        $totp->setDigits(6);

        return $totp->at(max(0, now()->getTimestamp()));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function adminJson(string $method, string $uri, array $payload = [], ?string $token = null): TestResponse
    {
        $request = $this->withHeader('Host', 'localhost');
        if ($token !== null) {
            $request = $request->withToken($token);
        }

        return $request->{$method.'Json'}($uri, $payload);
    }

    private function withAdminToken(string $token): self
    {
        return $this->withHeader('Host', 'localhost')->withToken($token);
    }
}
