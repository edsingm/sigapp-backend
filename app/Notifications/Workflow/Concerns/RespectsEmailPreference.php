<?php

declare(strict_types=1);

namespace App\Notifications\Workflow\Concerns;

use App\Models\Tenant\User;
use App\Notifications\NotificationCatalog;
use App\Services\Tenant\NotificationPreferenceService;

/**
 * Resolve o canal `mail` conforme a preferência de e-mail do usuário para a
 * categoria da notificação. Destinatários que não são usuários do tenant
 * (ex.: envio on-demand) recebem normalmente.
 *
 * Quando o usuário optou por resumo (digest diário/semanal), o e-mail imediato
 * é suprimido apenas se a categoria também está no inbox (canal in-app), de modo
 * que o resumo possa ser montado a partir do inbox sem perder a notificação.
 */
trait RespectsEmailPreference
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $preferences = app(NotificationPreferenceService::class);
        $category = $this->notificationCategory();

        if (! $preferences->isEnabled($notifiable, $category, NotificationCatalog::CHANNEL_EMAIL)) {
            return [];
        }

        $usesDigest = $preferences->emailDigestFrequency($notifiable) !== NotificationPreferenceService::DIGEST_INSTANT;
        $inInbox = $preferences->isEnabled($notifiable, $category, NotificationCatalog::CHANNEL_IN_APP);

        if ($usesDigest && $inInbox) {
            return [];
        }

        return ['mail'];
    }

    abstract protected function notificationCategory(): string;
}
