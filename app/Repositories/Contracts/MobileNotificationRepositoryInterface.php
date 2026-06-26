<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\MobileNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

interface MobileNotificationRepositoryInterface
{
    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, MobileNotification>
     */
    public function createdSinceForUser(int $userId, Carbon $since): Collection;

    public function findForUser(int $userId, string $notificationId): MobileNotification;

    public function findByDedupeKey(int $userId, string $dedupeKey): ?MobileNotification;

    public function create(array $data): MobileNotification;

    public function markAsRead(MobileNotification $notification): MobileNotification;

    public function recordDeliveryError(MobileNotification $notification, string $error): MobileNotification;

    public function countUnreadForUser(int $userId): int;

    public function markAllAsReadForUser(int $userId): int;

    public function deleteForUser(int $userId, string $notificationId): void;
}
