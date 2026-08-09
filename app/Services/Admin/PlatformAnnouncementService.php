<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Central\PlatformAnnouncement;
use App\Models\Central\PlatformAnnouncementDismissal;
use App\Models\Central\Tenant;
use App\Notifications\PlatformAnnouncementNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
     *     type?: string,
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
            'type' => $data['type'] ?? PlatformAnnouncement::TYPE_INFO,
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
     *     type?: string,
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
            // Banner-only: marca como sent para consumo in-app no tenant.
            $announcement->update([
                'status' => PlatformAnnouncement::STATUS_SENT,
                'sent_at' => now(),
                'recipients_count' => 0,
                'meta' => ['note' => 'Canal banner: disponível no app do tenant'],
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
                    (string) ($announcement->type ?: PlatformAnnouncement::TYPE_INFO),
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
     * @return Collection<int, Tenant>
     */
    private function resolveRecipients(PlatformAnnouncement $announcement): Collection
    {
        /** @var Collection<int, Tenant> $recipients */
        $recipients = $this->recipientsQuery($announcement)
            ->whereNotNull('admin_email')
            ->where('admin_email', '!=', '')
            ->get();

        return $recipients;
    }

    /**
     * Anúncios ativos com banner para um tenant (canal banner|both, status sent).
     *
     * @return Collection<int, PlatformAnnouncement>
     */
    public function activeBannersForTenant(Tenant $tenant, int $userId): Collection
    {
        $dismissedIds = PlatformAnnouncementDismissal::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('user_id', $userId)
            ->pluck('announcement_id')
            ->all();

        $query = PlatformAnnouncement::query()
            ->where('status', PlatformAnnouncement::STATUS_SENT)
            ->whereIn('channel', [
                PlatformAnnouncement::CHANNEL_BANNER,
                PlatformAnnouncement::CHANNEL_BOTH,
            ])
            ->where(function (Builder $q) use ($tenant): void {
                $q->where('segment', PlatformAnnouncement::SEGMENT_ALL)
                    ->orWhere(function (Builder $q2) use ($tenant): void {
                        $q2->where('segment', PlatformAnnouncement::SEGMENT_PLAN)
                            ->where('segment_value', (string) $tenant->getAttribute('plan_id'));
                    })
                    ->orWhere(function (Builder $q2) use ($tenant): void {
                        $q2->where('segment', PlatformAnnouncement::SEGMENT_STATUS)
                            ->where('segment_value', (string) $tenant->getAttribute('status'));
                    });
            })
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(20);

        if ($dismissedIds !== []) {
            $query->whereNotIn('id', $dismissedIds);
        }

        // Prioridade visual: segurança > manutenção > update > info > promo
        /** @var Collection<int, PlatformAnnouncement> $announcements */
        $announcements = $query->get();

        return $announcements->sortBy(function (PlatformAnnouncement $a): array {
            return [
                PlatformAnnouncement::typePriority((string) $a->type),
                // sent_at mais recente primeiro (invertido no sortBy com timestamp negativo)
                -($a->sent_at?->getTimestamp() ?? 0),
                -$a->id,
            ];
        })->values()->take(5);
    }

    public function dismissForUser(
        PlatformAnnouncement $announcement,
        Tenant $tenant,
        int $userId
    ): void {
        if (! in_array($announcement->channel, [
            PlatformAnnouncement::CHANNEL_BANNER,
            PlatformAnnouncement::CHANNEL_BOTH,
        ], true)) {
            throw new InvalidArgumentException('Este anúncio não possui banner.');
        }

        if ($announcement->status !== PlatformAnnouncement::STATUS_SENT) {
            throw new InvalidArgumentException('Anúncio não está ativo.');
        }

        PlatformAnnouncementDismissal::query()->firstOrCreate([
            'announcement_id' => $announcement->id,
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => $userId,
        ]);
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
