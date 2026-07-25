<?php

declare(strict_types=1);

namespace App\Notifications\Workflow;

use App\Notifications\Workflow\Concerns\RespectsEmailPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Queue;

#[Queue('notifications')]
class ViabilidadeSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsEmailPreference;

    public function __construct(
        private readonly string $terrenoNome,
        private readonly int $viabilidadeId,
        private readonly int $terrenoId,
    ) {}

    protected function notificationCategory(): string
    {
        return 'viabilidade.submetida';
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
