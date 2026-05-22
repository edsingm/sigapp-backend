<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $resetUrl,
        private readonly int $expireMinutes,
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
            ->subject('Redefina sua senha no SIG.APP')
            ->view('emails.tenant-reset-password', [
                'resetUrl' => $this->resetUrl,
                'expireMinutes' => $this->expireMinutes,
            ])
            ->text('emails.plain.tenant-reset-password', [
                'resetUrl' => $this->resetUrl,
                'expireMinutes' => $this->expireMinutes,
            ]);
    }
}
