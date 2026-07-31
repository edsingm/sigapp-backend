<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant\ReportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Queue;

#[Queue('notifications')]
class ReportScheduleReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ReportRun $run,
        private readonly string $scheduleName,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $downloadUrl = url('/api/v1/reports/runs/'.$this->run->id.'/download');
        $format = strtoupper($this->run->format ?: 'csv');

        return (new MailMessage)
            ->subject('SIG.APP — Relatório agendado pronto: '.$this->scheduleName)
            ->greeting('Olá'.(isset($notifiable->name) ? ', '.$notifiable->name : '').'!')
            ->line('O relatório agendado **'.$this->scheduleName.'** foi gerado com sucesso.')
            ->line('Formato: '.$format)
            ->line('O arquivo expira em 24 horas.')
            ->action('Baixar relatório', $downloadUrl)
            ->line('Se o botão não funcionar, acesse o construtor de relatórios no SIGAPP.');
    }
}
