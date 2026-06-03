<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\MobileDeviceInstallation;
use App\Repositories\Contracts\MobileDeviceInstallationRepositoryInterface;
use Illuminate\Support\Collection;

class MobileDeviceInstallationRepository implements MobileDeviceInstallationRepositoryInterface
{
    public function updateOrCreateByInstallationId(string $installationId, array $attributes): MobileDeviceInstallation
    {
        return MobileDeviceInstallation::query()->updateOrCreate(
            ['installation_id' => $installationId],
            $attributes,
        );
    }

    public function reassignToUser(MobileDeviceInstallation $device, int $userId): MobileDeviceInstallation
    {
        $device->forceFill(['user_id' => $userId])->save();

        /** @var MobileDeviceInstallation $fresh */
        $fresh = $device->fresh();

        return $fresh;
    }

    public function deleteForUser(int $userId, string $installationId): void
    {
        MobileDeviceInstallation::query()
            ->where('user_id', $userId)
            ->where('installation_id', $installationId)
            ->delete();
    }

    public function getTokensForUser(int $userId): Collection
    {
        return MobileDeviceInstallation::query()
            ->where('user_id', $userId)
            ->whereNotNull('expo_push_token')
            ->pluck('expo_push_token')
            ->filter()
            ->unique()
            ->values();
    }
}
