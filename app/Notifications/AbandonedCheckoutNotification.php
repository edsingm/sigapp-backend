<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AbandonedCheckoutNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $tenantName,
        private readonly string $planSlug,
        private readonly ?string $signupUrl,
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
        $newSignupUrl = $this->signupUrl
            ?? rtrim((string) config('app.frontend_url', config('app.url')), '/').'/cadastro';

        return (new MailMessage)
            ->subject('SIG.APP — Sua conta foi removida')
            ->view('emails.abandoned-checkout', [
                'tenantName' => $this->tenantName,
                'planSlug' => $this->planSlug,
                'signupUrl' => $newSignupUrl,
            ])
            ->text('emails.plain.abandoned-checkout', [
                'tenantName' => $this->tenantName,
                'planSlug' => $this->planSlug,
                'signupUrl' => $newSignupUrl,
            ]);
    }
}
