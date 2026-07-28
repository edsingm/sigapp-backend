<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Central\PlatformAnnouncement;
use App\Models\Central\Tenant;
use App\Notifications\PlatformAnnouncementNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class PlatformAnnouncementService
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return PlatformAnnouncement::query()
            ->with('author:id,name,email')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @param  array{
     *     title: string,
     *     body: string,
     *     channel: string,
     *     segment: string,
     *     segment_value?: string|null,
     *     created_by?: int|null
     * }  $data
     */
    public function createDraft(array $data): PlatformAnnouncement
    {
        return PlatformAnnouncement::query()->create([
            'title' => $data['title'],
            'body' => $data['body'],
            'channel' => $data['channel'],
            'segment' => $data['segment'],
            'segment_value' => $data['segment_value'] ?? null,
            'status' => PlatformAnnouncement::STATUS_DRAFT,
            'recipients_count' => 0,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /**
     * @param  array{
     *     title?: string,
     *     body?: string,
     *     channel?: string,
     *     segment?: string,
     *     segment_value?: string|null
     * }  $data
     */
    public function updateDraft(PlatformAnnouncement $announcement, array $data): PlatformAnnouncement
    {
        if ($announcement->status !== PlatformAnnouncement::STATUS_DRAFT) {
            throw new InvalidArgumentException('Somente rascunhos podem ser editados.');
        }

        $announcement->fill($data);
        $announcement->save();

        return $announcement->refresh();
    }

    public function deleteDraft(PlatformAnnouncement $announcement): void
    {
        if ($announcement->status !== PlatformAnnouncement::STATUS_DRAFT) {
            throw new InvalidArgumentException('Somente rascunhos podem ser removidos.');
        }

        $announcement->delete();
    }

    /**
     * Envia o anúncio (e-mail) para o segmento. Síncrono em chunks para MVP.
     */
    public function send(PlatformAnnouncement $announcement): PlatformAnnouncement
    {
        if ($announcement->status === PlatformAnnouncement::STATUS_SENT) {
            throw new InvalidArgumentException('Este anúncio já foi enviado.');
        }

        if (! in_array($announcement->channel, [
            PlatformAnnouncement::CHANNEL_EMAIL,
            PlatformAnnouncement::CHANNEL_BOTH,
        ], true)) {
            // Banner-only: apenas marca como sent (consumo in-app futuro)
            $announcement->update([
                'status' => PlatformAnnouncement::STATUS_SENT,
                'sent_at' => now(),
                'recipients_count' => 0,
                'meta' => ['note' => 'Canal banner: sem envio de e-mail no MVP'],
            ]);

            return $announcement->refresh();
        }

        $announcement->update(['status' => PlatformAnnouncement::STATUS_SENDING]);

        $tenants = $this->resolveRecipients($announcement);
        $sent = 0;
        $errors = [];

        foreach ($tenants as $tenant) {
            $email = $tenant->routeNotificationForMail();
            if (! is_string($email) || $email === '') {
                continue;
            }

            try {
                $tenant->notify(new PlatformAnnouncementNotification(
                    $announcement->title,
                    $announcement->body,
                    (string) $tenant->getAttribute('name'),
                ));
                $sent++;
            } catch (Throwable $e) {
                $errors[] = [
                    'tenant_id' => $tenant->getKey(),
                    'error' => $e->getMessage(),
                ];
                Log::warning('[PlatformAnnouncement] Falha ao notificar tenant', [
                    'tenant_id' => $tenant->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $announcement->update([
            'status' => $sent > 0 || $errors === []
                ? PlatformAnnouncement::STATUS_SENT
                : PlatformAnnouncement::STATUS_FAILED,
            'sent_at' => now(),
            'recipients_count' => $sent,
            'meta' => [
                'errors_sample' => array_slice($errors, 0, 10),
                'errors_count' => count($errors),
            ],
        ]);

        return $announcement->refresh();
    }

    /**
     * Preview de quantos tenants receberiam o anúncio.
     */
    public function estimateRecipients(PlatformAnnouncement $announcement): int
    {
        return $this->recipientsQuery($announcement)
            ->whereNotNull('admin_email')
            ->where('admin_email', '!=', '')
            ->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    private function resolveRecipients(PlatformAnnouncement $announcement)
    {
        return $this->recipientsQuery($announcement)
            ->whereNotNull('admin_email')
            ->where('admin_email', '!=', '')
            ->get();
    }

    /**
     * @return Builder<Tenant>
     */
    private function recipientsQuery(PlatformAnnouncement $announcement): Builder
    {
        $query = Tenant::query();

        return match ($announcement->segment) {
            PlatformAnnouncement::SEGMENT_PLAN => $query->where('plan_id', (int) $announcement->segment_value),
            PlatformAnnouncement::SEGMENT_STATUS => $query->where('status', (string) $announcement->segment_value),
            default => $query,
        };
    }
}
