<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Queue;

#[Queue('notifications')]
class TenantUserInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $inviteUrl,
        private readonly int $expireMinutes,
        private readonly string $userName,
        private readonly string $tenantName,
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
            ->subject("Você foi convidado para o SIG.APP — {$this->tenantName}")
            ->view('emails.tenant-user-invite', [
                'inviteUrl' => $this->inviteUrl,
                'expireMinutes' => $this->expireMinutes,
                'userName' => $this->userName,
                'tenantName' => $this->tenantName,
            ])
            ->text('emails.plain.tenant-user-invite', [
                'inviteUrl' => $this->inviteUrl,
                'expireMinutes' => $this->expireMinutes,
                'userName' => $this->userName,
                'tenantName' => $this->tenantName,
            ]);
    }
}
