<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\MobileDeviceInstallation;
use Illuminate\Support\Collection;

interface MobileDeviceInstallationRepositoryInterface
{
    public function updateOrCreateByInstallationId(string $installationId, array $attributes): MobileDeviceInstallation;

    public function reassignToUser(MobileDeviceInstallation $device, int $userId): MobileDeviceInstallation;

    public function deleteForUser(int $userId, string $installationId): void;

    /**
     * @return Collection<int, string>
     */
    public function getTokensForUser(int $userId): Collection;
}
