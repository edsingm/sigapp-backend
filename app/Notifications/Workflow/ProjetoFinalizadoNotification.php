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
class ProjetoFinalizadoNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsEmailPreference;

    public function __construct(
        private readonly string $projetoNome,
        private readonly int $projetoId,
    ) {}

    protected function notificationCategory(): string
    {
        return 'projeto.finalizado';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return (new MailMessage)
            ->subject('SIG.APP — Projeto finalizado')
            ->view('emails.projeto-finalizado', [
                'projetoNome' => $this->projetoNome,
                'projetoId' => $this->projetoId,
                'frontendUrl' => $frontendUrl,
            ])
            ->text('emails.plain.projeto-finalizado', [
                'projetoNome' => $this->projetoNome,
                'projetoId' => $this->projetoId,
                'frontendUrl' => $frontendUrl,
            ]);
    }
}
