<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\MobileNotification;
use App\Repositories\Contracts\MobileNotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MobileNotificationRepository implements MobileNotificationRepositoryInterface
{
    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator
    {
        return MobileNotification::query()
            ->where('user_id', $userId)
            ->latest()
            ->paginate($perPage);
    }

    public function findForUser(int $userId, string $notificationId): MobileNotification
    {
        return MobileNotification::query()
            ->where('user_id', $userId)
            ->findOrFail($notificationId);
    }

    public function findByDedupeKey(int $userId, string $dedupeKey): ?MobileNotification
    {
        return MobileNotification::query()
            ->where('user_id', $userId)
            ->where('dedupe_key', $dedupeKey)
            ->first();
    }

    public function create(array $data): MobileNotification
    {
        return MobileNotification::query()->create($data);
    }

    public function markAsRead(MobileNotification $notification): MobileNotification
    {
        if (! $notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        /** @var MobileNotification $fresh */
        $fresh = $notification->fresh();

        return $fresh;
    }

    public function recordDeliveryError(MobileNotification $notification, string $error): MobileNotification
    {
        $notification->forceFill(['delivery_error' => $error])->save();

        /** @var MobileNotification $fresh */
        $fresh = $notification->fresh();

        return $fresh;
    }
}
