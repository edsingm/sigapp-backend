<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Queue;

#[Queue('notifications')]
class DisputeCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly int $amount,
        public readonly string $reason,
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
        $amountFormatted = 'R$ '.number_format($this->amount / 100, 2, ',', '.');

        return (new MailMessage)
            ->subject('Conta em revisão — contestação de pagamento recebida')
            ->greeting("Olá, {$this->tenantName}")
            ->line('Recebemos uma contestação de pagamento (chargeback) referente à sua assinatura.')
            ->line("Valor contestado: {$amountFormatted}")
            ->line('Sua conta foi temporariamente colocada em revisão enquanto analisamos a situação.')
            ->line('Nossa equipe entrará em contato em breve. Se tiver dúvidas, acesse o suporte.')
            ->action('Falar com o suporte', rtrim((string) config('app.frontend_url'), '/').'/suporte')
            ->line('Se você reconhece esta cobrança, por favor, entre em contato imediatamente.');
    }
}
