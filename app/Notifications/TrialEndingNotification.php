<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $tenantName,
        private readonly Carbon $trialEndsAt,
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
        $daysLeft = max(0, (int) now()->diffInDays($this->trialEndsAt, false));
        $formattedDate = $this->trialEndsAt->format('d/m/Y');

        $daysText = $daysLeft === 1 ? '1 dia' : "{$daysLeft} dias";

        return (new MailMessage)
            ->subject("SIG.APP — Seu período de teste termina em {$daysText}")
            ->view('emails.trial-ending', [
                'tenantName' => $this->tenantName,
                'formattedDate' => $formattedDate,
                'daysText' => $daysText,
                'billingUrl' => rtrim((string) config('app.frontend_url', config('app.url')), '/').'/billing',
            ]);
    }
}
