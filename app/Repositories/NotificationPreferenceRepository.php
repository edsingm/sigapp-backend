<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\NotificationPreference;
use App\Repositories\Contracts\NotificationPreferenceRepositoryInterface;

class NotificationPreferenceRepository implements NotificationPreferenceRepositoryInterface
{
    public function mapForUser(int $userId): array
    {
        return NotificationPreference::query()
            ->where('user_id', $userId)
            ->get(['category', 'channel', 'enabled'])
            ->mapWithKeys(fn (NotificationPreference $pref) => [
                "{$pref->category}|{$pref->channel}" => (bool) $pref->enabled,
            ])
            ->all();
    }

    public function upsert(int $userId, string $category, string $channel, bool $enabled): void
    {
        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $userId, 'category' => $category, 'channel' => $channel],
            ['enabled' => $enabled],
        );
    }
}
