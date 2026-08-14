<?php

declare(strict_types=1);

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Queue;

#[Queue('notifications')]
class TenantWipeUpcomingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantName,
        private readonly CarbonInterface $wipeScheduledAt,
        private readonly int $daysRemaining,
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
        $formattedDate = $this->wipeScheduledAt->timezone(config('app.timezone'))->format('d/m/Y');
        $daysText = $this->daysRemaining === 1 ? '1 dia' : $this->daysRemaining.' dias';

        return (new MailMessage)
            ->subject("SIG.APP — seus dados serão removidos em {$daysText}")
            ->greeting('Olá, '.$this->tenantName)
            ->line("O workspace foi cancelado e os dados (schema e arquivos) serão apagados em {$formattedDate}.")
            ->line('Se ainda precisa dos dados, solicite o dump de portabilidade ao administrador da plataforma antes dessa data.')
            ->line('Este aviso não cancela a assinatura Stripe e não apaga identificadores fiscais de cobrança.');
    }
}
