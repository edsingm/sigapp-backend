<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Queue;

#[Queue('notifications')]
class AdminMfaChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $action) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable instanceof User ? $notifiable->name : '';
        $label = match ($this->action) {
            'setup' => 'configurado',
            'rotate' => 'alterado',
            'recovery_codes' => 'regenerado',
            'reset' => 'redefinido',
            default => 'alterado',
        };

        return (new MailMessage)
            ->subject('Alteração de segurança no SIGAPP')
            ->greeting('Olá'.($name !== '' ? ', '.$name : '').'!')
            ->line("O MFA da sua conta administrativa foi {$label}.")
            ->line('Se você não reconhece esta ação, contate imediatamente o responsável pela plataforma.')
            ->line('Esta mensagem não contém segredos ou códigos de recuperação.');
    }
}
