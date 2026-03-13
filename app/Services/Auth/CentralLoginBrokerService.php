<?php

namespace App\Services\Auth;

use App\Models\Central\CentralLoginBrokerSession;
use App\Models\Central\LoginTransferTicket;
use App\Models\Central\Tenant;
use App\Models\Central\TenantUserDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CentralLoginBrokerService
{
    private const TRANSFER_TICKET_TTL_SECONDS = 90;
    private const BROKER_SESSION_TTL_SECONDS = 300;

    public function __construct(
        private readonly TenantUserDirectoryService $directoryService,
    ) {
    }

    /**
     * Attempt central broker login across active tenants.
     *
     * @return array<string, mixed>
     */
    public function attemptCentralLogin(string $email, string $password, ?string $deviceName, Request $request): array
    {
        $startedAt = microtime(true);
        $candidates = $this->directoryService->candidatesForEmail($email);
        $matches = $this->findMatchingTenants($candidates, $password);
        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);

        Log::info('Central broker login attempt completed', [
            'request_id' => $request->header('X-Request-ID'),
            'matches_count' => count($matches),
            'candidate_tenants' => $candidates->count(),
            'duration_ms' => $durationMs,
        ]);

        if (count($matches) === 0) {
            return [
                'next_action' => 'unauthorized',
            ];
        }

        if (count($matches) === 1) {
            return $this->buildRedirectResponse($matches[0], $deviceName, $request);
        }

        $session = $this->createBrokerSession($email, $deviceName, $request, $matches);

        return [
            'next_action' => 'choose_tenant',
            'broker_session_id' => $session->id,
            'expires_at' => $session->expires_at?->toIso8601String(),
            'tenants' => array_map(function (array $match) {
                return [
                    'id' => $match['tenant_id'],
                    'name' => $match['tenant_name'],
                    'slug' => $match['tenant_slug'],
                    'tenant_url' => $match['tenant_url'],
                ];
            }, $matches),
        ];
    }

    /**
     * Complete central broker login when user selects a tenant.
     *
     * @return array<string, mixed>|null
     */
    public function selectTenant(string $brokerSessionId, string $tenantId, ?string $deviceName, Request $request): ?array
    {
        $session = CentralLoginBrokerSession::find($brokerSessionId);

        if (!$session || $session->isExpired() || $session->isCompleted()) {
            return null;
        }

        $options = is_array($session->tenant_options) ? $session->tenant_options : [];
        $selected = collect($options)->firstWhere('tenant_id', $tenantId);

        if (!is_array($selected)) {
            return null;
        }

        $session->update(['completed_at' => now()]);

        if ($deviceName !== null && $deviceName !== '') {
            $selected['device_name'] = $deviceName;
        }

        return $this->buildRedirectResponse($selected, $selected['device_name'] ?? null, $request);
    }

    /**
     * Redeem a one-time transfer ticket in tenant context and create a Sanctum token.
     *
     * @return array<string, mixed>|null
     */
    public function redeemTransferTicket(string $rawTicket, ?string $deviceName, Request $request): ?array
    {
        $tenant = tenant();

        if (!$tenant) {
            return null;
        }

        $ticketHash = hash('sha256', $rawTicket);

        $ticket = tenancy()->central(function () use ($ticketHash) {
            return LoginTransferTicket::where('ticket_hash', $ticketHash)->first();
        });

        if (!$ticket instanceof LoginTransferTicket) {
            return null;
        }

        if ((string) $ticket->tenant_id !== (string) $tenant->id || $ticket->isUsed() || $ticket->isExpired()) {
            return null;
        }

        $user = \App\Models\Tenant\User::find($ticket->tenant_user_id);

        if (!$user && $ticket->email) {
            $user = \App\Models\Tenant\User::where('email', $ticket->email)->first();
        }

        if (!$user) {
            return null;
        }

        $tokenName = $deviceName ?: ($ticket->device_name ?: 'api-token');
        $tokenResult = $user->createToken($tokenName, ['tenant-api'], now()->addDays(7));

        $updated = tenancy()->central(function () use ($ticket) {
            return LoginTransferTicket::whereKey($ticket->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['used_at' => now()]);
        });

        if ($updated !== 1) {
            $tokenResult->accessToken->delete();

            return null;
        }

        Log::info('Login transfer ticket redeemed', [
            'tenant_id' => (string) $tenant->id,
            'tenant_user_id' => (string) $user->id,
        ]);

        return [
            'user' => $user,
            'token' => $tokenResult->plainTextToken,
            'abilities' => ['tenant-api'],
            'expires_at' => $tokenResult->accessToken->expires_at?->toIso8601String(),
        ];
    }

    /**
     * Cleanup expired/used broker records.
     *
     * @return array<string, int>
     */
    public function cleanup(): array
    {
        $cutoff = now()->subHours(1);

        $deletedSessions = CentralLoginBrokerSession::query()
            ->where(function ($query) {
                $query->where('expires_at', '<', now())
                    ->orWhereNotNull('completed_at');
            })
            ->where('updated_at', '<', $cutoff)
            ->delete();

        $deletedTickets = LoginTransferTicket::query()
            ->where(function ($query) {
                $query->where('expires_at', '<', now())
                    ->orWhereNotNull('used_at');
            })
            ->where('updated_at', '<', $cutoff)
            ->delete();

        return [
            'broker_sessions' => $deletedSessions,
            'transfer_tickets' => $deletedTickets,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findMatchingTenants(iterable $candidates, string $password): array
    {
        $matches = [];

        foreach ($candidates as $candidateDirectory) {
            if (!$candidateDirectory instanceof TenantUserDirectory) {
                continue;
            }

            $tenant = $candidateDirectory->tenant;

            if (!$tenant instanceof Tenant || !$tenant->isActive()) {
                continue;
            }

            try {
                $candidate = $tenant->run(function () use ($candidateDirectory, $password) {
                    $user = \App\Models\Tenant\User::query()
                        ->whereKey($candidateDirectory->tenant_user_id)
                        ->first();

                    if (!$user || !Hash::check($password, $user->password)) {
                        return null;
                    }

                    return [
                        'tenant_user_id' => (string) $user->getKey(),
                        'user_name' => (string) $user->name,
                    ];
                });
            } catch (\Throwable $e) {
                Log::warning('Central broker skipped tenant during auth attempt', [
                    'tenant_id' => (string) $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (!is_array($candidate)) {
                continue;
            }

            $tenantUrl = $this->resolveTenantUrl($tenant);
            if (!$tenantUrl) {
                continue;
            }

            $matches[] = [
                'tenant_id' => (string) $tenant->id,
                'tenant_name' => (string) $tenant->name,
                'tenant_slug' => (string) $tenant->slug,
                'tenant_url' => $tenantUrl,
                'tenant_user_id' => (string) $candidate['tenant_user_id'],
                'user_name' => (string) $candidate['user_name'],
                'email' => $candidateDirectory->email_normalized,
            ];
        }

        return $matches;
    }

    /**
     * @param array<string, mixed> $match
     * @return array<string, mixed>
     */
    private function buildRedirectResponse(array $match, ?string $deviceName, Request $request): array
    {
        $ticket = $this->issueTransferTicket(
            tenantId: (string) $match['tenant_id'],
            tenantUserId: (string) $match['tenant_user_id'],
            email: (string) $match['email'],
            deviceName: $deviceName,
            request: $request
        );

        return [
            'next_action' => 'redirect',
            'tenant' => [
                'id' => $match['tenant_id'],
                'name' => $match['tenant_name'],
                'slug' => $match['tenant_slug'],
            ],
            'tenant_url' => $match['tenant_url'],
            'transfer_ticket' => $ticket['ticket'],
            'transfer_ticket_expires_at' => $ticket['expires_at'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $matches
     */
    private function createBrokerSession(string $email, ?string $deviceName, Request $request, array $matches): CentralLoginBrokerSession
    {
        return CentralLoginBrokerSession::create([
            'id' => (string) Str::uuid(),
            'email' => $email,
            'device_name' => $deviceName,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 2000, ''),
            'tenant_options' => $matches,
            'expires_at' => now()->addSeconds(self::BROKER_SESSION_TTL_SECONDS),
        ]);
    }

    /**
     * @return array{ticket:string, expires_at:string}
     */
    private function issueTransferTicket(
        string $tenantId,
        string $tenantUserId,
        string $email,
        ?string $deviceName,
        Request $request
    ): array {
        $rawTicket = Str::random(96);
        $expiresAt = now()->addSeconds(self::TRANSFER_TICKET_TTL_SECONDS);

        LoginTransferTicket::create([
            'id' => (string) Str::uuid(),
            'ticket_hash' => hash('sha256', $rawTicket),
            'tenant_id' => $tenantId,
            'tenant_user_id' => $tenantUserId,
            'email' => $email,
            'device_name' => $deviceName,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 2000, ''),
            'expires_at' => $expiresAt,
        ]);

        Log::info('Login transfer ticket issued', [
            'tenant_id' => $tenantId,
            'tenant_user_id' => $tenantUserId,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return [
            'ticket' => $rawTicket,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    private function resolveTenantUrl(Tenant $tenant): ?string
    {
        $domain = $tenant->domains()->orderBy('id')->value('domain');

        if (!is_string($domain) || $domain === '') {
            $baseDomain = (string) (config('tenancy.identification.central_domains')[0] ?? config('app.domain') ?? '');
            if ($baseDomain === '') {
                return null;
            }

            $domain = $tenant->slug . '.' . $baseDomain;
        }

        if (Str::startsWith($domain, ['http://', 'https://'])) {
            return rtrim($domain, '/');
        }

        $scheme = $this->preferredScheme();

        return $scheme . '://' . $domain;
    }

    private function preferredScheme(): string
    {
        $appUrl = (string) config('app.url', '');
        if ($appUrl !== '') {
            $scheme = parse_url($appUrl, PHP_URL_SCHEME);
            if (is_string($scheme) && $scheme !== '') {
                return $scheme;
            }
        }

        return app()->environment('local') ? 'http' : 'https';
    }
}
