<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\User;
use App\Models\Tenant\UserOnboardingEvent;
use App\Models\Tenant\UserOnboardingState;

class UserOnboardingRepository
{
    public function state(User $user): UserOnboardingState
    {
        return UserOnboardingState::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['catalog_version' => 'v1', 'profile' => 'analyst', 'completed_steps' => []],
        );
    }

    /** @param array<string, mixed> $data */
    public function recordEvent(User $user, array $data): ?UserOnboardingEvent
    {
        $existing = UserOnboardingEvent::query()
            ->where('user_id', $user->id)
            ->where('event_id', $data['event_id'])
            ->first();
        if ($existing) {
            return null;
        }

        return UserOnboardingEvent::query()->create([
            'user_id' => $user->id,
            'event_id' => $data['event_id'],
            'event' => $data['event'],
            'metadata' => $data['metadata'] ?? [],
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);
    }
}
