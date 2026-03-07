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
    ) {
    }

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
            ->greeting('Olá!')
            ->line('Recebemos uma solicitação para redefinir a senha da sua conta.')
            ->action('Redefinir senha', $this->resetUrl)
            ->line("Este link expira em {$this->expireMinutes} minutos.")
            ->line('Se você não solicitou a redefinição, pode ignorar este e-mail com segurança.');
    }
}
