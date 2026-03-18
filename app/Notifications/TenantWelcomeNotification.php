<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantWelcomeNotification extends Notification
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
            ->greeting('Olá!')
            ->line("Sua conta **{$this->tenantName}** foi criada e está pronta para uso.")
            ->action('Acessar o SIG.APP', $this->appUrl)
            ->line('Se tiver dúvidas, entre em contato com o nosso suporte.')
            ->salutation('Equipe SIG.APP');
    }
}
