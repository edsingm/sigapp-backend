<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DemoRequestReceived;
use App\Notifications\DemoRequestNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class NotifyDemoRequestReceived
{
    public function handle(DemoRequestReceived $event): void
    {
        $recipient = trim((string) config('app.central_admin.email'));

        if ($recipient === '') {
            Log::warning('Solicitação de demonstração registrada sem destinatário de notificação configurado.');

            return;
        }

        try {
            Notification::route('mail', $recipient)
                ->notify(new DemoRequestNotification($event->demoRequest));
        } catch (Throwable $exception) {
            // A captura do lead já foi persistida; falha no e-mail não deve
            // transformar uma solicitação válida em erro para o visitante.
            report($exception);
        }
    }
}
