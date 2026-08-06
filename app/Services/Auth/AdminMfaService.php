<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\Common\AdminMfaChallengePurpose;
use App\Enums\Common\AdminMfaChallengeStatus;
use App\Events\AdminMfaChanged;
use App\Exceptions\AdminMfaException;
use App\Models\Central\AdminMfaChallenge;
use App\Models\Central\AdminMfaRecoveryCode;
use App\Models\User;
use App\Repositories\AdminMfaAuditRepository;
use App\Repositories\Contracts\AdminMfaRepositoryInterface;
use App\Repositories\Contracts\CentralUserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use OTPHP\TOTP;

class AdminMfaService
{
    private const TOTP_PERIOD = 30;

    private const TOTP_DIGITS = 6;

    private const MAX_CHALLENGE_ATTEMPTS = 5;

    private const RECOVERY_CODE_COUNT = 10;

    public function __construct(
        private readonly AdminMfaRepositoryInterface $repository,
        private readonly CentralUserRepositoryInterface $userRepository,
        private readonly AdminMfaAuditRepository $auditRepository,
    ) {}

    /**
     * @return array{
     *     user: User,
     *     state: string,
     *     challenge: string,
     *     expires_at: string,
     *     setup?: array{otpauth_uri: string, manual_key: string}
     * }
     */
    public function beginLogin(
        User $user,
        string $deviceName,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        return DB::transaction(function () use ($user, $deviceName, $ipAddress, $userAgent): array {
            $lockedUser = $this->repository->lockUser((int) $user->getKey());
            $isSetup = ! $lockedUser->admin_mfa_confirmed_at || ! is_string($lockedUser->admin_mfa_secret);
            $purpose = $isSetup ? AdminMfaChallengePurpose::SETUP : AdminMfaChallengePurpose::LOGIN;
            $this->repository->invalidatePendingChallenges((int) $lockedUser->getKey(), $purpose);

            $challengeToken = bin2hex(random_bytes(32));
            $pendingSecret = null;
            $setup = null;

            if ($isSetup) {
                $totp = $this->newTotp();
                $totp->setIssuer($this->issuer());
                $totp->setLabel($lockedUser->email);
                $pendingSecret = $totp->getSecret();
                $setup = [
                    'otpauth_uri' => $totp->getProvisioningUri(),
                    'manual_key' => $pendingSecret,
                ];
            }

            $expiresAt = now()->addMinutes($isSetup ? 10 : 5);
            $this->repository->createChallenge([
                'user_id' => $lockedUser->getKey(),
                'token_hash' => $this->hashChallenge($challengeToken),
                'purpose' => $purpose,
                'status' => AdminMfaChallengeStatus::PENDING,
                'factor_version' => $lockedUser->admin_mfa_version,
                'pending_secret' => $pendingSecret,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_name' => $this->normalizeDeviceName($deviceName),
                'expires_at' => $expiresAt,
            ]);

            return [
                'user' => $lockedUser,
                'state' => $isSetup ? 'MFA_SETUP_REQUIRED' : 'MFA_REQUIRED',
                'challenge' => $challengeToken,
                'expires_at' => $expiresAt->toIso8601String(),
                ...($setup !== null ? ['setup' => $setup] : []),
            ];
        });
    }

    /**
     * @return array{user: User, token: string, expires_at: string|null, recovery_codes?: list<string>}
     */
    public function verifyLogin(
        string $challengeToken,
        ?string $totpCode,
        ?string $recoveryCode,
    ): array {
        $result = DB::transaction(function () use ($challengeToken, $totpCode, $recoveryCode): array {
            $challenge = $this->repository->findChallengeForUpdate($this->hashChallenge($challengeToken));

            if ($challenge === null) {
                return ['error' => 'MFA_CHALLENGE_INVALID'];
            }

            $user = $this->repository->lockUser((int) $challenge->user_id);
            if (! $user->is_admin) {
                return ['error' => 'MFA_CHALLENGE_INVALID'];
            }

            if ($this->accountRateLimited($user)) {
                return ['error' => 'MFA_RATE_LIMITED'];
            }

            if (
                ! $challenge->isPending()
                || $challenge->isExpired()
                || (int) $challenge->factor_version !== (int) $user->admin_mfa_version
                || $challenge->attempts >= self::MAX_CHALLENGE_ATTEMPTS
            ) {
                $this->repository->invalidateChallenge($challenge);

                return ['error' => 'MFA_CHALLENGE_INVALID'];
            }

            $purpose = $challenge->purpose;
            $step = null;
            $recovery = null;

            if ($purpose === AdminMfaChallengePurpose::SETUP) {
                $step = $this->matchedTotpStep($challenge->pending_secret, $totpCode);
            } elseif ($purpose === AdminMfaChallengePurpose::LOGIN) {
                if ($recoveryCode !== null) {
                    $recovery = $this->findRecoveryCode($user, $recoveryCode);
                } else {
                    $step = $this->matchedTotpStep($user->admin_mfa_secret, $totpCode);
                }
            }

            if ($step === null && $recovery === null) {
                return $this->registerInvalidCode($challenge, $user);
            }

            if (
                $step !== null
                && $user->admin_mfa_last_used_timestep !== null
                && $step <= (int) $user->admin_mfa_last_used_timestep
            ) {
                return $this->registerInvalidCode($challenge, $user);
            }

            if (! $this->repository->consumeChallenge($challenge)) {
                return ['error' => 'MFA_CHALLENGE_INVALID'];
            }

            $recoveryCodes = null;
            if ($purpose === AdminMfaChallengePurpose::SETUP) {
                if ($user->admin_mfa_confirmed_at || ! is_string($challenge->pending_secret)) {
                    return ['error' => 'MFA_CHALLENGE_INVALID'];
                }

                $recoveryCodes = $this->replaceRecoveryCodes($user);
                $user = $this->repository->updateMfa($user, [
                    'admin_mfa_secret' => $challenge->pending_secret,
                    'admin_mfa_confirmed_at' => now(),
                    'admin_mfa_last_used_timestep' => $step,
                    'admin_mfa_version' => ((int) $user->admin_mfa_version) + 1,
                ]);
                $this->repository->invalidatePendingChallenges((int) $user->getKey(), AdminMfaChallengePurpose::SETUP);
                $this->repository->revokeTokens($user);
                $this->auditRepository->record((int) $user->getKey(), 'admin.mfa.setup');
            } else {
                if ($recovery instanceof AdminMfaRecoveryCode && ! $this->repository->consumeRecoveryCode($recovery)) {
                    throw new AdminMfaException('MFA_CODE_INVALID', 'MFA_CODE_INVALID', 422);
                }

                if ($recovery instanceof AdminMfaRecoveryCode) {
                    $this->auditRepository->record((int) $user->getKey(), 'admin.mfa.recovery.use', [
                        'purpose' => $purpose->value,
                    ]);
                }

                if ($step !== null) {
                    $user = $this->repository->updateMfa($user, [
                        'admin_mfa_last_used_timestep' => $step,
                    ]);
                }
            }

            RateLimiter::clear($this->accountRateLimitKey($user));

            $tokenData = $this->issueToken($user, (string) ($challenge->device_name ?? 'admin-token'));

            return [
                'user' => $user,
                ...$tokenData,
                ...($recoveryCodes !== null ? ['recovery_codes' => $recoveryCodes] : []),
                'event_action' => $purpose === AdminMfaChallengePurpose::SETUP ? 'setup' : null,
            ];
        });

        if (isset($result['error'])) {
            $this->throwVerificationError((string) $result['error']);
        }

        if (($result['event_action'] ?? null) === 'setup' && $result['user'] instanceof User) {
            AdminMfaChanged::dispatch($result['user'], 'setup');
        }

        unset($result['event_action']);

        /** @var array{user: User, token: string, expires_at: string|null, recovery_codes?: list<string>} $result */
        return $result;
    }

    /**
     * @return array{challenge: string, expires_at: string, setup: array{otpauth_uri: string, manual_key: string}}
     */
    public function beginRotation(
        User $user,
        string $password,
        ?string $totpCode,
        ?string $recoveryCode,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        $result = DB::transaction(function () use ($user, $password, $totpCode, $recoveryCode, $ipAddress, $userAgent): array {
            $lockedUser = $this->repository->lockUser((int) $user->getKey());
            if (! $lockedUser->is_admin) {
                return ['error' => 'MFA_REAUTH_FAILED'];
            }
            if (! Hash::check($password, (string) $lockedUser->getRawOriginal('password'))) {
                return ['error' => 'MFA_REAUTH_FAILED'];
            }
            if ($this->accountRateLimited($lockedUser)) {
                return ['error' => 'MFA_RATE_LIMITED'];
            }

            $factorResult = $this->validateCurrentFactor($lockedUser, $totpCode, $recoveryCode);
            if (isset($factorResult['error'])) {
                return $factorResult;
            }
            RateLimiter::clear($this->accountRateLimitKey($lockedUser));
            if ($factorResult['step'] === null) {
                $this->auditRepository->record((int) $lockedUser->getKey(), 'admin.mfa.recovery.use', [
                    'purpose' => 'rotate',
                ]);
            }

            $totp = $this->newTotp();
            $totp->setIssuer($this->issuer());
            $totp->setLabel($lockedUser->email);
            $challengeToken = bin2hex(random_bytes(32));
            $expiresAt = now()->addMinutes(10);

            $this->repository->invalidatePendingChallenges((int) $lockedUser->getKey(), AdminMfaChallengePurpose::ROTATE);
            $this->repository->createChallenge([
                'user_id' => $lockedUser->getKey(),
                'token_hash' => $this->hashChallenge($challengeToken),
                'purpose' => AdminMfaChallengePurpose::ROTATE,
                'status' => AdminMfaChallengeStatus::PENDING,
                'factor_version' => $lockedUser->admin_mfa_version,
                'pending_secret' => $totp->getSecret(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_name' => 'admin-token',
                'expires_at' => $expiresAt,
            ]);

            return [
                'challenge' => $challengeToken,
                'expires_at' => $expiresAt->toIso8601String(),
                'setup' => [
                    'otpauth_uri' => $totp->getProvisioningUri(),
                    'manual_key' => $totp->getSecret(),
                ],
            ];
        });

        if (isset($result['error'])) {
            $this->throwFactorError((string) $result['error']);
        }

        /** @var array{challenge: string, expires_at: string, setup: array{otpauth_uri: string, manual_key: string}} $result */
        return $result;
    }

    /**
     * @return array{user: User, token: string, expires_at: string|null, recovery_codes: list<string>}
     */
    public function verifyRotation(User $actor, string $challengeToken, ?string $totpCode): array
    {
        $result = DB::transaction(function () use ($actor, $challengeToken, $totpCode): array {
            $challenge = $this->repository->findChallengeForUpdate($this->hashChallenge($challengeToken));
            if ($challenge === null) {
                return ['error' => 'MFA_CHALLENGE_INVALID'];
            }

            if ((int) $challenge->user_id !== (int) $actor->getKey()) {
                return ['error' => 'MFA_CHALLENGE_INVALID'];
            }

            $user = $this->repository->lockUser((int) $challenge->user_id);
            if (! $user->is_admin) {
                return ['error' => 'MFA_CHALLENGE_INVALID'];
            }
            if ($this->accountRateLimited($user)) {
                return ['error' => 'MFA_RATE_LIMITED'];
            }
            if (
                ! $challenge->isPending()
                || $challenge->purpose !== AdminMfaChallengePurpose::ROTATE
                || $challenge->isExpired()
                || (int) $challenge->factor_version !== (int) $user->admin_mfa_version
                || ! is_string($challenge->pending_secret)
            ) {
                $this->repository->invalidateChallenge($challenge);

                return ['error' => 'MFA_CHALLENGE_INVALID'];
            }

            $step = $this->matchedTotpStep($challenge->pending_secret, $totpCode);
            if ($step === null) {
                return $this->registerInvalidCode($challenge, $user);
            }

            if (! $this->repository->consumeChallenge($challenge)) {
                return ['error' => 'MFA_CHALLENGE_INVALID'];
            }

            $recoveryCodes = $this->replaceRecoveryCodes($user);
            $user = $this->repository->updateMfa($user, [
                'admin_mfa_secret' => $challenge->pending_secret,
                'admin_mfa_confirmed_at' => now(),
                'admin_mfa_last_used_timestep' => $step,
                'admin_mfa_version' => ((int) $user->admin_mfa_version) + 1,
            ]);
            $this->repository->invalidatePendingChallenges((int) $user->getKey());
            $this->repository->revokeTokens($user);
            $this->auditRepository->record((int) $user->getKey(), 'admin.mfa.rotate');

            return [
                'user' => $user,
                ...$this->issueToken($user, (string) ($challenge->device_name ?? 'admin-token')),
                'recovery_codes' => $recoveryCodes,
                'event_action' => 'rotate',
            ];
        });

        if (isset($result['error'])) {
            $this->throwVerificationError((string) $result['error']);
        }

        if ($result['user'] instanceof User) {
            AdminMfaChanged::dispatch($result['user'], 'rotate');
        }

        unset($result['event_action']);

        /** @var array{user: User, token: string, expires_at: string|null, recovery_codes: list<string>} $result */
        return $result;
    }

    /** @return array{enabled: bool, recovery_codes_remaining: int} */
    public function status(User $user): array
    {
        return [
            'enabled' => $user->admin_mfa_confirmed_at !== null && is_string($user->admin_mfa_secret),
            'recovery_codes_remaining' => $this->repository->countUnusedRecoveryCodes((int) $user->getKey()),
        ];
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(
        User $user,
        string $password,
        ?string $totpCode,
        ?string $recoveryCode,
    ): array {
        $result = DB::transaction(function () use ($user, $password, $totpCode, $recoveryCode): array {
            $lockedUser = $this->repository->lockUser((int) $user->getKey());
            if (! $lockedUser->is_admin) {
                return ['error' => 'MFA_REAUTH_FAILED'];
            }
            if (! Hash::check($password, (string) $lockedUser->getRawOriginal('password'))) {
                return ['error' => 'MFA_REAUTH_FAILED'];
            }
            if ($this->accountRateLimited($lockedUser)) {
                return ['error' => 'MFA_RATE_LIMITED'];
            }

            $factorResult = $this->validateCurrentFactor($lockedUser, $totpCode, $recoveryCode);
            if (isset($factorResult['error'])) {
                return $factorResult;
            }
            RateLimiter::clear($this->accountRateLimitKey($lockedUser));

            $codes = $this->replaceRecoveryCodes($lockedUser);
            $this->repository->updateMfa($lockedUser, [
                'admin_mfa_version' => ((int) $lockedUser->admin_mfa_version) + 1,
            ]);
            $this->repository->invalidatePendingChallenges((int) $lockedUser->getKey());
            if ($factorResult['step'] === null) {
                $this->auditRepository->record((int) $lockedUser->getKey(), 'admin.mfa.recovery.use', [
                    'purpose' => 'recovery_codes',
                ]);
            }
            $this->auditRepository->record((int) $lockedUser->getKey(), 'admin.mfa.recovery_codes');

            return ['codes' => $codes, 'user' => $lockedUser, 'event_action' => 'recovery_codes'];
        });

        if (isset($result['error'])) {
            $this->throwFactorError((string) $result['error']);
        }

        if ($result['user'] instanceof User) {
            AdminMfaChanged::dispatch($result['user'], 'recovery_codes');
        }

        /** @var array{codes: list<string>} $result */
        return $result['codes'];
    }

    public function reset(string $email, string $operator, string $reason): void
    {
        $user = $this->userRepository->findByEmail(mb_strtolower(trim($email)));
        if (! $user instanceof User || ! $user->is_admin) {
            throw new AdminMfaException('ADMIN_MFA_USER_NOT_FOUND', 'ADMIN_MFA_USER_NOT_FOUND', 404);
        }

        DB::transaction(function () use ($user, $operator, $reason): void {
            $lockedUser = $this->repository->lockUser((int) $user->getKey());
            $this->repository->updateMfa($lockedUser, [
                'admin_mfa_secret' => null,
                'admin_mfa_confirmed_at' => null,
                'admin_mfa_last_used_timestep' => null,
                'admin_mfa_version' => ((int) $lockedUser->admin_mfa_version) + 1,
            ]);
            $this->repository->deleteRecoveryCodes((int) $lockedUser->getKey());
            $this->repository->invalidatePendingChallenges((int) $lockedUser->getKey());
            $this->repository->revokeTokens($lockedUser);
            $this->auditRepository->record((int) $lockedUser->getKey(), 'admin.mfa.reset', [
                'operator' => $operator,
                'reason' => $reason,
            ]);
        });

        AdminMfaChanged::dispatch($user, 'reset');
    }

    /**
     * @return array{step: int|null}|array{error: string}
     */
    private function validateCurrentFactor(User $user, ?string $totpCode, ?string $recoveryCode): array
    {
        if ($recoveryCode !== null) {
            $code = $this->findRecoveryCode($user, $recoveryCode);
            if (! $code instanceof AdminMfaRecoveryCode || ! $this->repository->consumeRecoveryCode($code)) {
                RateLimiter::hit($this->accountRateLimitKey($user), 600);
                $this->auditRepository->record((int) $user->getKey(), 'admin.mfa.failure', [
                    'purpose' => 'reauth',
                ]);

                return ['error' => 'MFA_CODE_INVALID'];
            }

            return ['step' => null];
        }

        $step = $this->matchedTotpStep($user->admin_mfa_secret, $totpCode);
        if (
            $step === null
            || ($user->admin_mfa_last_used_timestep !== null && $step <= (int) $user->admin_mfa_last_used_timestep)
        ) {
            RateLimiter::hit($this->accountRateLimitKey($user), 600);
            $this->auditRepository->record((int) $user->getKey(), 'admin.mfa.failure', [
                'purpose' => 'reauth',
            ]);

            return ['error' => 'MFA_CODE_INVALID'];
        }

        $this->repository->updateMfa($user, ['admin_mfa_last_used_timestep' => $step]);

        return ['step' => $step];
    }

    /** @return array{error: string} */
    private function registerInvalidCode(AdminMfaChallenge $challenge, User $user): array
    {
        $this->repository->incrementChallengeAttempts($challenge);
        RateLimiter::hit($this->accountRateLimitKey($user), 600);
        if ($challenge->attempts >= self::MAX_CHALLENGE_ATTEMPTS) {
            $this->repository->invalidateChallenge($challenge);
        }
        $this->auditRepository->record((int) $user->getKey(), 'admin.mfa.failure', [
            'purpose' => $challenge->purpose->value,
            'attempts' => (int) $challenge->attempts,
        ]);

        return ['error' => 'MFA_CODE_INVALID'];
    }

    private function throwVerificationError(string $error): never
    {
        if ($error === 'MFA_RATE_LIMITED') {
            throw new AdminMfaException($error, $error, 429);
        }

        if ($error === 'MFA_CHALLENGE_INVALID') {
            throw new AdminMfaException($error, $error, 401);
        }

        throw new AdminMfaException($error, $error, 422);
    }

    private function throwFactorError(string $error): never
    {
        throw new AdminMfaException(
            $error,
            $error,
            $error === 'MFA_REAUTH_FAILED' ? 401 : ($error === 'MFA_RATE_LIMITED' ? 429 : 422),
        );
    }

    private function newTotp(): TOTP
    {
        $totp = TOTP::generate();
        $totp->setPeriod(self::TOTP_PERIOD);
        $totp->setDigits(self::TOTP_DIGITS);

        return $totp;
    }

    /** @return non-empty-string */
    private function issuer(): string
    {
        $issuer = trim((string) config('app.name', 'SIGAPP'));

        return $issuer !== '' ? $issuer : 'SIGAPP';
    }

    private function matchedTotpStep(?string $secret, ?string $code): ?int
    {
        if (! is_string($secret) || $secret === '' || ! is_string($code)) {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', trim($code));
        if (! is_string($normalized) || ! preg_match('/^\d{6}$/D', $normalized)) {
            return null;
        }

        $totp = TOTP::createFromSecret($secret);
        $totp->setPeriod(self::TOTP_PERIOD);
        $totp->setDigits(self::TOTP_DIGITS);
        $timestamp = now()->getTimestamp();

        foreach ([-1, 0, 1] as $offset) {
            $candidateTimestamp = $timestamp + ($offset * self::TOTP_PERIOD);
            if (hash_equals($totp->at(max(0, $candidateTimestamp)), $normalized)) {
                return intdiv($candidateTimestamp, self::TOTP_PERIOD);
            }
        }

        return null;
    }

    private function findRecoveryCode(User $user, string $code): ?AdminMfaRecoveryCode
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($code)) ?? '');
        if ($normalized === '') {
            return null;
        }

        /** @var Collection<int, AdminMfaRecoveryCode> $codes */
        $codes = $this->repository->unusedRecoveryCodesForUpdate((int) $user->getKey());
        foreach ($codes as $candidate) {
            if (Hash::check($normalized, (string) $candidate->code_hash)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function replaceRecoveryCodes(User $user): array
    {
        $this->repository->deleteRecoveryCodes((int) $user->getKey());
        $codes = [];

        for ($index = 0; $index < self::RECOVERY_CODE_COUNT; $index++) {
            $code = strtoupper(bin2hex(random_bytes(8)));
            $codes[] = $code;
            $this->repository->createRecoveryCode((int) $user->getKey(), Hash::make($code));
        }

        return $codes;
    }

    /** @return array{token: string, expires_at: string|null} */
    private function issueToken(User $user, string $deviceName): array
    {
        $name = $this->normalizeDeviceName($deviceName);
        $this->repository->revokeTokensByName($user, $name);
        $tokenResult = $user->createToken($name, ['admin', 'admin:mfa'], now()->addHours(12));
        $expiresAt = $tokenResult->accessToken->getAttribute('expires_at');

        return [
            'token' => $tokenResult->plainTextToken,
            'expires_at' => $expiresAt instanceof \DateTimeInterface ? $expiresAt->format(\DateTimeInterface::ATOM) : null,
        ];
    }

    private function hashChallenge(string $challenge): string
    {
        return hash('sha256', $challenge);
    }

    private function normalizeDeviceName(string $deviceName): string
    {
        $normalized = trim($deviceName);

        return $normalized !== '' ? mb_substr($normalized, 0, 255) : 'admin-token';
    }

    private function accountRateLimitKey(User $user): string
    {
        return 'admin-mfa:account:'.$user->getKey();
    }

    private function accountRateLimited(User $user): bool
    {
        return RateLimiter::tooManyAttempts($this->accountRateLimitKey($user), 10);
    }
}
