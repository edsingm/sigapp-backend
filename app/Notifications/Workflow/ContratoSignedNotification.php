<?php

declare(strict_types=1);

namespace App\Notifications\Workflow;

use App\Notifications\Workflow\Concerns\RespectsEmailPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContratoSignedNotification extends Notification
{
    use Queueable;
    use RespectsEmailPreference;

    public function __construct(
        private readonly string $terrenoNome,
        private readonly string $contratoId,
        private readonly int $terrenoId,
    ) {}

    protected function notificationCategory(): string
    {
        return 'contrato.assinado';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return (new MailMessage)
            ->subject('SIG.APP — Contrato assinado')
            ->view('emails.contrato-signed', [
                'terrenoNome' => $this->terrenoNome,
                'contratoId' => $this->contratoId,
                'terrenoId' => $this->terrenoId,
                'frontendUrl' => $frontendUrl,
            ])
            ->text('emails.plain.contrato-signed', [
                'terrenoNome' => $this->terrenoNome,
                'contratoId' => $this->contratoId,
                'terrenoId' => $this->terrenoId,
                'frontendUrl' => $frontendUrl,
            ]);
    }
}
