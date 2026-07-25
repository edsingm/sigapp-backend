<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Queue;

#[Queue('notifications')]
class TenantWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantName,
        private readonly string $appUrl,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Bem-vindo ao SIG.APP — {$this->tenantName}")
            ->view('emails.tenant-welcome', [
                'tenantName' => $this->tenantName,
                'appUrl' => $this->appUrl,
            ])
            ->text('emails.plain.tenant-welcome', [
                'tenantName' => $this->tenantName,
                'appUrl' => $this->appUrl,
            ]);
    }
}
