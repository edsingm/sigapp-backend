<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\MobileCapture;
use App\Models\Tenant\MobileCaptureAttachment;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Collection;

class MobileCaptureRepository
{
    public function findForUser(User $user, string $clientId): MobileCapture
    {
        return MobileCapture::query()
            ->where('user_id', $user->id)
            ->where('client_id', $clientId)
            ->with('attachments')
            ->firstOrFail();
    }

    public function findExisting(User $user, string $clientId): ?MobileCapture
    {
        return MobileCapture::query()
            ->where('user_id', $user->id)
            ->where('client_id', $clientId)
            ->first();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): MobileCapture
    {
        return MobileCapture::query()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(MobileCapture $capture, array $data): MobileCapture
    {
        $capture->update($data);

        return $capture->fresh(['attachments', 'terreno']) ?? $capture;
    }

    /** @return Collection<int, MobileCaptureAttachment> */
    public function attachments(MobileCapture $capture): Collection
    {
        return $capture->attachments()->latest('id')->get();
    }
}
