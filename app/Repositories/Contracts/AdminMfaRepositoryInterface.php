<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\Common\AdminMfaChallengePurpose;
use App\Models\Central\AdminMfaChallenge;
use App\Models\Central\AdminMfaRecoveryCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface AdminMfaRepositoryInterface
{
    public function lockUser(int $userId): User;

    /** @param array<string, mixed> $attributes */
    public function createChallenge(array $attributes): AdminMfaChallenge;

    public function findChallengeForUpdate(string $tokenHash): ?AdminMfaChallenge;

    public function incrementChallengeAttempts(AdminMfaChallenge $challenge): void;

    public function consumeChallenge(AdminMfaChallenge $challenge): bool;

    public function invalidateChallenge(AdminMfaChallenge $challenge): bool;

    public function invalidatePendingChallenges(
        int $userId,
        ?AdminMfaChallengePurpose $purpose = null,
    ): int;

    public function deleteExpiredChallenges(): int;

    /** @return Collection<int, AdminMfaRecoveryCode> */
    public function unusedRecoveryCodesForUpdate(int $userId): Collection;

    public function countUnusedRecoveryCodes(int $userId): int;

    public function deleteRecoveryCodes(int $userId): int;

    public function createRecoveryCode(int $userId, string $codeHash): AdminMfaRecoveryCode;

    public function consumeRecoveryCode(AdminMfaRecoveryCode $code): bool;

    /** @param array<string, mixed> $attributes */
    public function updateMfa(User $user, array $attributes): User;

    public function revokeTokens(User $user): int;

    public function revokeTokensByName(User $user, string $name): int;
}
