<?php

declare(strict_types=1);

namespace App\Notifications\Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ViabilidadeSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $terrenoNome,
        private readonly int $viabilidadeId,
        private readonly int $terrenoId,
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
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return (new MailMessage)
            ->subject('SIG.APP — Viabilidade aguardando aprovação')
            ->view('emails.viabilidade-submitted', [
                'terrenoNome' => $this->terrenoNome,
                'terrenoId' => $this->terrenoId,
                'viabilidadeId' => $this->viabilidadeId,
            ])
            ->text('emails.plain.viabilidade-submitted', [
                'terrenoNome' => $this->terrenoNome,
                'terrenoId' => $this->terrenoId,
                'viabilidadeId' => $this->viabilidadeId,
            ]);
    }
}
