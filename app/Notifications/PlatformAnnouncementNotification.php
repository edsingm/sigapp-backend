<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Queue;

#[Queue('notifications')]
class PlatformAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly string $tenantName,
        private readonly string $type = 'info',
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
        $typeLabel = match ($this->type) {
            'security' => 'Segurança',
            'promo' => 'Promoção',
            'maintenance' => 'Manutenção',
            'update' => 'Atualização',
            default => 'Informativo',
        };

        return (new MailMessage)
            ->subject("[SIGAPP · {$typeLabel}] {$this->title}")
            ->greeting('Olá'.($this->tenantName !== '' ? ", {$this->tenantName}" : '').'!')
            ->line("**{$typeLabel}**")
            ->line($this->body)
            ->line('Esta é uma comunicação da plataforma SIGAPP.');
    }
}
