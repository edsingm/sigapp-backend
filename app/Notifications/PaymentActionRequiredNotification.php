<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentActionRequiredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
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
            ->subject('Seu pagamento precisa de confirmação')
            ->greeting("Olá, {$this->tenantName}")
            ->line('O Stripe informou que a cobrança da sua assinatura precisa de uma ação adicional.')
            ->line('Isso normalmente acontece quando o banco exige autenticação extra do pagamento.')
            ->action('Concluir pagamento', $this->getActionUrl())
            ->line('Depois da confirmação, sua assinatura volta ao fluxo normal.');

        if ($this->invoiceUrl) {
            $message->line('Se preferir, você também pode abrir diretamente a fatura: '.$this->invoiceUrl);
        }

        return $message;
    }

    protected function getActionUrl(): string
    {
        return $this->invoiceUrl ?: rtrim((string) config('app.frontend_url'), '/').'/billing';
    }
}
