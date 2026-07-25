<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Queue;

#[Queue('notifications')]
class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantName,
        private readonly int $attemptCount,
        private readonly ?string $invoiceUrl,
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
            ->subject('SIG.APP — Falha no pagamento da sua assinatura')
            ->view('emails.payment-failed', [
                'tenantName' => $this->tenantName,
                'attemptCount' => $this->attemptCount,
                'invoiceUrl' => $this->invoiceUrl,
            ])
            ->text('emails.plain.payment-failed', [
                'tenantName' => $this->tenantName,
                'attemptCount' => $this->attemptCount,
                'invoiceUrl' => $this->invoiceUrl,
            ]);
    }
}
