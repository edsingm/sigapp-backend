<?php

declare(strict_types=1);

namespace App\Notifications\Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjetoFinalizadoNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $projetoNome,
        private readonly int $projetoId,
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
