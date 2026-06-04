<?php

declare(strict_types=1);

namespace App\Notifications\Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LegalizacaoEtapaStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $etapaTitulo,
        private readonly string $status,
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
        $terrenoInfo = $this->terrenoNome ? " do terreno {$this->terrenoNome}" : '';

        return (new MailMessage)
            ->subject("SIG.APP — Etapa de legalização atualizada{$terrenoInfo}")
            ->view('emails.legalizacao-etapa-status-updated', [
                'etapaTitulo' => $this->etapaTitulo,
                'status' => $this->status,
                'terrenoNome' => $this->terrenoNome,
                'terrenoId' => $this->terrenoId,
                'frontendUrl' => $frontendUrl,
            ])
            ->text('emails.plain.legalizacao-etapa-status-updated', [
                'etapaTitulo' => $this->etapaTitulo,
                'status' => $this->status,
                'terrenoNome' => $this->terrenoNome,
                'terrenoId' => $this->terrenoId,
                'frontendUrl' => $frontendUrl,
            ]);
    }
}
