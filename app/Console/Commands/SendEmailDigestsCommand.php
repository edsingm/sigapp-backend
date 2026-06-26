<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Models\Tenant\MobileNotification;
use App\Models\Tenant\User;
use App\Notifications\EmailDigestNotification;
use App\Notifications\NotificationCatalog;
use App\Repositories\Contracts\MobileNotificationRepositoryInterface;
use App\Services\Tenant\NotificationPreferenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendEmailDigestsCommand extends Command
{
    protected $signature = 'notifications:send-email-digests {frequency : daily ou weekly}';

    protected $description = 'Envia o resumo de notificações por e-mail (digest) aos usuários que optaram pela frequência informada';

    public function handle(
        MobileNotificationRepositoryInterface $notifications,
        NotificationPreferenceService $preferences,
    ): int {
        $frequency = (string) $this->argument('frequency');

        if (! in_array($frequency, [NotificationPreferenceService::DIGEST_DAILY, NotificationPreferenceService::DIGEST_WEEKLY], true)) {
            $this->error('Frequência inválida. Use "daily" ou "weekly".');

            return self::FAILURE;
        }

        $since = $frequency === NotificationPreferenceService::DIGEST_WEEKLY
            ? now()->subWeek()
            : now()->subDay();

        $sent = 0;

        Tenant::query()
            ->where('status', Tenant::STATUS_ACTIVE)
            ->get()
            ->each(function (Tenant $tenant) use ($notifications, $preferences, $frequency, $since, &$sent) {
                try {
                    $tenant->run(function () use ($notifications, $preferences, $frequency, $since, &$sent) {
                        User::query()
                            ->where('email_digest_frequency', $frequency)
                            ->each(function (User $user) use ($notifications, $preferences, $since, $frequency, &$sent) {
                                $items = $notifications->createdSinceForUser($user->id, $since)
                                    ->filter(function (MobileNotification $notification) use ($preferences, $user) {
                                        $category = NotificationCatalog::categoryForType($notification->type);

                                        return $category !== null
                                            && $preferences->isEnabled($user, $category, NotificationCatalog::CHANNEL_EMAIL);
                                    })
                                    ->map(fn (MobileNotification $notification) => [
                                        'title' => $notification->title,
                                        'body' => $notification->body,
                                        'target_route' => $notification->target_route,
                                        'created_at' => $notification->created_at?->toIso8601String(),
                                    ])
                                    ->values()
                                    ->all();

                                if ($items === []) {
                                    return;
                                }

                                $user->notify(new EmailDigestNotification($items, $frequency));
                                $sent++;
                            });
                    });
                } catch (\Throwable $exception) {
                    Log::warning('Erro ao enviar digest de notificações do tenant', [
                        'tenant_id' => $tenant->id,
                        'frequency' => $frequency,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });

        $this->info("Resumos enviados: {$sent}");

        return self::SUCCESS;
    }
}
