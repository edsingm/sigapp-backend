<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AdminMfaChanged;
use App\Notifications\AdminMfaChangedNotification;

class SendAdminMfaChangedNotification
{
    public function handle(AdminMfaChanged $event): void
    {
        $event->user->notify(new AdminMfaChangedNotification($event->action));
    }
}
