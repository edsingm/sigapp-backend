<?php

use App\Jobs\RefreshTenantStatsJob;
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

Schedule::command('auth:cleanup-central-login-broker')
    ->everyFiveMinutes()
    ->name('auth-cleanup-central-login-broker')
    ->onOneServer()
    ->withoutOverlapping(10);
Schedule::command('privacy:cleanup-consent-logs')
    ->daily()
    ->name('privacy-cleanup-consent-logs')
    ->onOneServer()
    ->withoutOverlapping(120);
Schedule::command('tenants:cleanup-pending')
    ->hourly()
    ->name('tenants-cleanup-pending')
    ->onOneServer()
    ->withoutOverlapping(60);
Schedule::command('tenant:notify-overdue-legalizacao-etapas')
    ->dailyAt('08:00')
    ->name('tenant-notify-overdue-legalizacao-etapas')
    ->onOneServer()
    ->withoutOverlapping(60);
Schedule::command('tenant:check-storage-usage')
    ->dailyAt('07:00')
    ->name('tenant-check-storage-usage')
    ->onOneServer()
    ->withoutOverlapping(120);
Schedule::command('notifications:send-email-digests daily')
    ->dailyAt('08:30')
    ->name('notifications-send-email-digests-daily')
    ->onOneServer()
    ->withoutOverlapping(120);
Schedule::command('notifications:send-email-digests weekly')
    ->weeklyOn(1, '08:30')
    ->name('notifications-send-email-digests-weekly')
    ->onOneServer()
    ->withoutOverlapping(120);
Schedule::command('ai:recalculate-scores')
    ->dailyAt('06:00')
    ->name('ai-recalculate-scores')
    ->onOneServer()
    ->withoutOverlapping(360);
Schedule::job(new RefreshTenantStatsJob)
    ->hourly()
    ->name('refresh-tenant-stats')
    ->onOneServer()
    ->withoutOverlapping(60);
Schedule::command('cache:prune-stale-tags redis')
    ->dailyAt('03:30')
    ->name('cache-prune-stale-redis-tags')
    ->onOneServer()
    ->withoutOverlapping(120);
