<?php

declare(strict_types=1);

namespace App\Notifications\Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ViabilidadeDecidedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $terrenoNome,
        private readonly string $decision,
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
            ->subject($this->decision === 'aprovada'
                ? 'SIG.APP — Viabilidade aprovada'
                : 'SIG.APP — Viabilidade reprovada')
            ->view('emails.viabilidade-decided', [
                'terrenoNome' => $this->terrenoNome,
                'decision' => $this->decision,
                'terrenoId' => $this->terrenoId,
                'aprovada' => $this->decision === 'aprovada',
            ])
            ->text('emails.plain.viabilidade-decided', [
                'terrenoNome' => $this->terrenoNome,
                'decision' => $this->decision,
                'terrenoId' => $this->terrenoId,
                'aprovada' => $this->decision === 'aprovada',
            ]);
    }
}
