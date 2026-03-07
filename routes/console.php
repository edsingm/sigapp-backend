<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('auth:cleanup-central-login-broker')->everyFiveMinutes();
Schedule::command('tenant:notify-overdue-legalizacao-etapas')->dailyAt('08:00');
