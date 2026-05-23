<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentRetryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly int $attemptCount,
        public readonly ?string $invoiceUrl = null,
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
        $message = (new MailMessage)
            ->subject($this->getSubject())
            ->greeting("Olá, {$this->tenantName}");

        if ($this->attemptCount >= 3) {
            $message->line('Sua conta foi suspensa devido a falhas consecutivas no pagamento.')
                ->line('Para reativar, atualize seu método de pagamento e tente novamente.')
                ->action('Atualizar Pagamento', $this->getBillingUrl())
                ->line('Se já realizou o pagamento, ignore esta mensagem.');
        } elseif ($this->attemptCount >= 2) {
            $message->line('Não foi possível processar o pagamento da sua assinatura.')
                ->line('Este é o segundo aviso. Sua conta será suspensa na próxima tentativa.')
                ->action('Atualizar Método de Pagamento', $this->getBillingUrl())
                ->line('Você também pode pagar diretamente: '.$this->invoiceUrl);
        } else {
            $message->line('Não foi possível processar o pagamento da sua assinatura.')
                ->line('O Stripe tentará novamente automaticamente nos próximos dias.')
                ->action('Atualizar Método de Pagamento', $this->getBillingUrl());

            if ($this->invoiceUrl) {
                $message->line('Ou pague diretamente: '.$this->invoiceUrl);
            }
        }

        return $message;
    }

    protected function getSubject(): string
    {
        return match (true) {
            $this->attemptCount >= 3 => 'Conta suspensa — ação necessária',
            $this->attemptCount >= 2 => '2ª tentativa de pagamento falhou — atenção',
            default => 'Pagamento não processado',
        };
    }

    protected function getBillingUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/billing';
    }
}
