<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Laravel\Cashier\Payment;

/**
 * Notificação enviada pelo Cashier quando um pagamento requer autenticação adicional (SCA/3DS).
 * Configurada via CASHIER_PAYMENT_NOTIFICATION no .env.
 */
class PaymentRequiresActionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Payment $payment,
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
        return (new MailMessage)
            ->subject('SIG.APP — Confirmação de pagamento necessária')
            ->view('emails.payment-requires-action', [
                'paymentUrl' => $this->payment->url,
            ])
            ->text('emails.plain.payment-requires-action', [
                'paymentUrl' => $this->payment->url,
            ]);
    }
}
