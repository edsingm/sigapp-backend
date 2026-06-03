<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\MobileNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MobileNotificationRepositoryInterface
{
    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator;

    public function findForUser(int $userId, string $notificationId): MobileNotification;

    public function findByDedupeKey(int $userId, string $dedupeKey): ?MobileNotification;

    public function create(array $data): MobileNotification;

    public function markAsRead(MobileNotification $notification): MobileNotification;

    public function recordDeliveryError(MobileNotification $notification, string $error): MobileNotification;
}
