<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Events\DemoRequestReceived;
use App\Listeners\NotifyDemoRequestReceived;
use App\Models\Central\DemoRequest;
use App\Notifications\DemoRequestNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DemoRequestNotificationTest extends TestCase
{
    public function test_notification_uses_mail_and_contains_lead_company_in_subject(): void
    {
        $demoRequest = new DemoRequest([
            'name' => 'Edson',
            'email' => 'edson@example.com',
            'company' => 'Empresa Teste',
        ]);

        $notification = new DemoRequestNotification($demoRequest);

        $this->assertContains('mail', $notification->via(new \stdClass));
        $mail = $notification->toMail(new \stdClass);

        $this->assertStringContainsString('Empresa Teste', (string) $mail->subject);
    }

    public function test_listener_notifies_configured_central_admin(): void
    {
        Notification::fake();
        config(['app.central_admin.email' => 'admin@example.com']);

        $demoRequest = new DemoRequest([
            'name' => 'Edson',
            'email' => 'edson@example.com',
            'company' => 'Empresa Teste',
        ]);

        (new NotifyDemoRequestReceived)->handle(new DemoRequestReceived($demoRequest));

        Notification::assertSentOnDemand(DemoRequestNotification::class);
    }
}
