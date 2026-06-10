<?php

namespace Tests\Feature;

use App\Models\ConsentLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsentLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_consent_id_is_updated_instead_of_creating_duplicates(): void
    {
        $consentId = (string) Str::uuid();

        $firstResponse = $this
            ->withHeader('Host', 'localhost')
            ->withHeader('User-Agent', 'Browser A')
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.10'])
            ->postJson('/api/v1/consent-log', [
                'consent_id' => $consentId,
                'categories' => [
                    'functional' => true,
                    'analytics' => false,
                    'marketing' => false,
                ],
                'version' => '1.0',
                'timestamp' => now()->subMinute()->toIso8601String(),
            ]);

        $firstResponse->assertCreated();

        $secondTimestamp = now()->toIso8601String();

        $secondResponse = $this
            ->withHeader('Host', 'localhost')
            ->withHeader('User-Agent', 'Browser B')
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.10'])
            ->postJson('/api/v1/consent-log', [
                'consent_id' => $consentId,
                'categories' => [
                    'functional' => true,
                    'analytics' => true,
                    'marketing' => true,
                ],
                'version' => '1.1',
                'timestamp' => $secondTimestamp,
            ]);

        $secondResponse->assertOk();
        $this->assertDatabaseCount('consent_logs', 1);

        $consentLog = ConsentLog::query()->firstOrFail();

        $this->assertSame($consentId, $consentLog->consent_id);
        $this->assertSame('1.1', $consentLog->version);
        $this->assertSame([
            'functional' => true,
            'analytics' => true,
            'marketing' => true,
        ], $consentLog->categories);
        $this->assertSame('Browser B', $consentLog->user_agent);
        $this->assertTrue($consentLog->consented_at->equalTo(Carbon::parse($secondTimestamp)));
    }

    public function test_consent_log_route_uses_dedicated_aggressive_throttle(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this
                ->withHeader('Host', 'localhost')
                ->withServerVariables(['REMOTE_ADDR' => '127.0.0.20'])
                ->postJson('/api/v1/consent-log', [
                    'consent_id' => (string) Str::uuid(),
                    'categories' => [
                        'functional' => true,
                        'analytics' => false,
                        'marketing' => false,
                    ],
                    'version' => '1.0',
                    'timestamp' => now()->toIso8601String(),
                ]);

            $response->assertCreated();
        }

        $rateLimitedResponse = $this
            ->withHeader('Host', 'localhost')
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.20'])
            ->postJson('/api/v1/consent-log', [
                'consent_id' => (string) Str::uuid(),
                'categories' => [
                    'functional' => true,
                    'analytics' => false,
                    'marketing' => false,
                ],
                'version' => '1.0',
                'timestamp' => now()->toIso8601String(),
            ]);

        $rateLimitedResponse->assertStatus(429);
    }

    public function test_cleanup_command_removes_only_expired_consent_logs(): void
    {
        Config::set('privacy.consent_log_retention_days', 30);

        $expiredLog = ConsentLog::query()->create([
            'consent_id' => (string) Str::uuid(),
            'categories' => [
                'functional' => true,
                'analytics' => false,
                'marketing' => false,
            ],
            'version' => '1.0',
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent' => 'Browser A',
            'consented_at' => now()->subDays(31),
        ]);

        $activeLog = ConsentLog::query()->create([
            'consent_id' => (string) Str::uuid(),
            'categories' => [
                'functional' => true,
                'analytics' => true,
                'marketing' => false,
            ],
            'version' => '1.0',
            'ip_hash' => hash('sha256', '127.0.0.2'),
            'user_agent' => 'Browser B',
            'consented_at' => now()->subDays(5),
        ]);

        $this->artisan('privacy:cleanup-consent-logs')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('consent_logs', ['id' => $expiredLog->id]);
        $this->assertDatabaseHas('consent_logs', ['id' => $activeLog->id]);
    }
}
