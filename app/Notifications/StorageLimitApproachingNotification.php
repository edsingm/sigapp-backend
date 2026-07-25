<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Queue;

#[Queue('notifications')]
class StorageLimitApproachingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantName,
        private readonly float $usedGb,
        private readonly int $limitGb,
        private readonly int $percentage,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $usedFormatted = number_format($this->usedGb, 1, ',', '.');

        return (new MailMessage)
            ->subject("SIG.APP — Seu armazenamento está em {$this->percentage}% do limite do plano")
            ->view('emails.storage-limit-approaching', [
                'tenantName' => $this->tenantName,
                'usedFormatted' => $usedFormatted,
                'limitGb' => $this->limitGb,
                'percentage' => $this->percentage,
                'billingUrl' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/billing',
            ])
            ->text('emails.plain.storage-limit-approaching', [
                'tenantName' => $this->tenantName,
                'usedFormatted' => $usedFormatted,
                'limitGb' => $this->limitGb,
                'percentage' => $this->percentage,
                'billingUrl' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/billing',
            ]);
    }
}
