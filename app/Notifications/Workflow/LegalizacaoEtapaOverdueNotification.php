<?php

declare(strict_types=1);

namespace App\Notifications\Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LegalizacaoEtapaOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $etapaNome,
        private readonly ?string $terrenoNome,
        private readonly ?int $terrenoId,
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
            ->subject('SIG.APP — Etapa de legalização atrasada')
            ->view('emails.legalizacao-etapa-overdue', [
                'etapaNome' => $this->etapaNome,
                'terrenoNome' => $this->terrenoNome,
                'terrenoId' => $this->terrenoId,
                'frontendUrl' => $frontendUrl,
            ])
            ->text('emails.plain.legalizacao-etapa-overdue', [
                'etapaNome' => $this->etapaNome,
                'terrenoNome' => $this->terrenoNome,
                'terrenoId' => $this->terrenoId,
                'frontendUrl' => $frontendUrl,
            ]);
    }
}
