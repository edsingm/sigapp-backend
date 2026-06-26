<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailDigestNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{title: string, body: string, target_route: ?string, created_at: ?string}>  $items
     */
    public function __construct(
        private readonly array $items,
        private readonly string $frequency,
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
        $periodo = $this->frequency === 'weekly' ? 'da semana' : 'do dia';
        $total = count($this->items);

        return (new MailMessage)
            ->subject("SIG.APP — Resumo de notificações ({$total})")
            ->view('emails.notification-digest', [
                'items' => $this->items,
                'periodo' => $periodo,
                'total' => $total,
            ])
            ->text('emails.plain.notification-digest', [
                'items' => $this->items,
                'periodo' => $periodo,
                'total' => $total,
            ]);
    }
}
