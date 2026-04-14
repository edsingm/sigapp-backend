<?php

use App\Notifications\TenantWelcomeNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mail:test {email}', function (string $email) {
    $this->info("Enviando e-mail de teste para {$email} via Resend...");

    Notification::route('mail', $email)->notify(
        new TenantWelcomeNotification('Tenant Teste', config('app.url'))
    );

    $this->info('Notificação enviada com sucesso para a fila/transporte.');
})->purpose('Testa o envio de e-mails via Resend');

Schedule::command('auth:cleanup-central-login-broker')->everyFiveMinutes();
Schedule::command('tenants:cleanup-pending')->hourly();
Schedule::command('tenant:notify-overdue-legalizacao-etapas')->dailyAt('08:00');
Schedule::command('ai:recalculate-scores')->dailyAt('06:00');
