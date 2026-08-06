<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Common\AdminMfaChallengePurpose;
use App\Enums\Common\AdminMfaChallengeStatus;
use App\Models\Central\AdminMfaChallenge;
use App\Models\Central\AdminMfaRecoveryCode;
use App\Models\User;
use App\Repositories\Contracts\AdminMfaRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AdminMfaRepository implements AdminMfaRepositoryInterface
{
    public function lockUser(int $userId): User
    {
        /** @var User $user */
        $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

        return $user;
    }

    public function createChallenge(array $attributes): AdminMfaChallenge
    {
        return AdminMfaChallenge::query()->create($attributes);
    }

    public function findChallengeForUpdate(string $tokenHash): ?AdminMfaChallenge
    {
        /** @var AdminMfaChallenge|null $challenge */
        $challenge = AdminMfaChallenge::query()
            ->where('token_hash', $tokenHash)
            ->lockForUpdate()
            ->first();

        return $challenge;
    }

    public function incrementChallengeAttempts(AdminMfaChallenge $challenge): void
    {
        AdminMfaChallenge::query()->whereKey($challenge->getKey())->increment('attempts');
        $challenge->refresh();
    }

    public function consumeChallenge(AdminMfaChallenge $challenge): bool
    {
        $updated = AdminMfaChallenge::query()
            ->whereKey($challenge->getKey())
            ->where('status', AdminMfaChallengeStatus::PENDING->value)
            ->update([
                'status' => AdminMfaChallengeStatus::CONSUMED->value,
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 1) {
            $challenge->refresh();
        }

        return $updated === 1;
    }

    public function invalidateChallenge(AdminMfaChallenge $challenge): bool
    {
        $updated = AdminMfaChallenge::query()
            ->whereKey($challenge->getKey())
            ->where('status', AdminMfaChallengeStatus::PENDING->value)
            ->update([
                'status' => AdminMfaChallengeStatus::INVALIDATED->value,
                'invalidated_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 1) {
            $challenge->refresh();
        }

        return $updated === 1;
    }

    public function invalidatePendingChallenges(
        int $userId,
        ?AdminMfaChallengePurpose $purpose = null,
    ): int {
        $query = AdminMfaChallenge::query()
            ->where('user_id', $userId)
            ->where('status', AdminMfaChallengeStatus::PENDING->value);

        if ($purpose instanceof AdminMfaChallengePurpose) {
            $query->where('purpose', $purpose->value);
        }

        return $query->update([
            'status' => AdminMfaChallengeStatus::INVALIDATED->value,
            'invalidated_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function deleteExpiredChallenges(): int
    {
        return AdminMfaChallenge::query()
            ->where(function ($query): void {
                $query
                    ->where('expires_at', '<=', now())
                    ->orWhere(function ($query): void {
                        $query
                            ->whereIn('status', [
                                AdminMfaChallengeStatus::CONSUMED->value,
                                AdminMfaChallengeStatus::INVALIDATED->value,
                            ])
                            ->where('updated_at', '<=', now()->subDay());
                    });
            })
            ->delete();
    }

    /** @return Collection<int, AdminMfaRecoveryCode> */
    public function unusedRecoveryCodesForUpdate(int $userId): Collection
    {
        /** @var Collection<int, AdminMfaRecoveryCode> $codes */
        $codes = AdminMfaRecoveryCode::query()
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->lockForUpdate()
            ->get();

        return $codes;
    }

    public function countUnusedRecoveryCodes(int $userId): int
    {
        return (int) AdminMfaRecoveryCode::query()
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->count();
    }

    public function deleteRecoveryCodes(int $userId): int
    {
        return AdminMfaRecoveryCode::query()->where('user_id', $userId)->delete();
    }

    public function createRecoveryCode(int $userId, string $codeHash): AdminMfaRecoveryCode
    {
        return AdminMfaRecoveryCode::query()->create([
            'user_id' => $userId,
            'code_hash' => $codeHash,
        ]);
    }

    public function consumeRecoveryCode(AdminMfaRecoveryCode $code): bool
    {
        return AdminMfaRecoveryCode::query()
            ->whereKey($code->getKey())
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]) === 1;
    }

    public function updateMfa(User $user, array $attributes): User
    {
        $user->forceFill($attributes)->save();

        return $user->refresh();
    }

    public function revokeTokens(User $user): int
    {
        return $user->tokens()->delete();
    }

    public function revokeTokensByName(User $user, string $name): int
    {
        return $user->tokens()->where('name', $name)->delete();
    }
}
