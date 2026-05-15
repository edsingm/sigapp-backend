<?php

namespace Tests\Unit\Notifications;

use App\Notifications\AbandonedCheckoutNotification;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\PaymentRequiresActionNotification;
use App\Notifications\TenantResetPasswordNotification;
use App\Notifications\TenantWelcomeNotification;
use App\Notifications\TrialEndingNotification;
use Carbon\Carbon;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    // -----------------------------------------------------------------
    // TenantWelcomeNotification
    // -----------------------------------------------------------------

    public function test_welcome_notification_via_mail(): void
    {
        $notification = new TenantWelcomeNotification('Empresa Teste', 'https://app.sigapp.com.br');

        $this->assertContains('mail', $notification->via(new \stdClass()));
    }

    public function test_w_notification_aceita_tenant_name_e_app_url(): void
    {
        $notification = new TenantWelcomeNotification('Empresa Teste', 'https://app.sigapp.com.br');

        $reflection = new \ReflectionClass($notification);
        $tenantName = $reflection->getProperty('tenantName');
        $appUrl = $reflection->getProperty('appUrl');

        $this->assertSame('Empresa Teste', $tenantName->getValue($notification));
        $this->assertSame('https://app.sigapp.com.br', $appUrl->getValue($notification));
    }

    // -----------------------------------------------------------------
    // PaymentFailedNotification
    // -----------------------------------------------------------------

    public function test_payment_failed_via_mail(): void
    {
        $notification = new PaymentFailedNotification('Empresa', 1, 'https://invoice.stripe.com');

        $this->assertContains('mail', $notification->via(new \stdClass()));
    }

    public function test_payment_failed_aceita_parametros(): void
    {
        $notification = new PaymentFailedNotification('Empresa', 3, 'https://invoice.stripe.com');

        $reflection = new \ReflectionClass($notification);

        $this->assertSame('Empresa', $reflection->getProperty('tenantName')->getValue($notification));
        $this->assertSame(3, $reflection->getProperty('attemptCount')->getValue($notification));
        $this->assertSame('https://invoice.stripe.com', $reflection->getProperty('invoiceUrl')->getValue($notification));
    }

    public function test_payment_failed_invoice_url_pode_ser_null(): void
    {
        $notification = new PaymentFailedNotification('Empresa', 1, null);

        $reflection = new \ReflectionClass($notification);

        $this->assertNull($reflection->getProperty('invoiceUrl')->getValue($notification));
    }

    // -----------------------------------------------------------------
    // TrialEndingNotification
    // -----------------------------------------------------------------

    public function test_trial_ending_via_mail(): void
    {
        $notification = new TrialEndingNotification('Empresa', Carbon::now()->addDays(3));

        $this->assertContains('mail', $notification->via(new \stdClass()));
    }

    public function test_trial_ending_aceita_tenant_name_e_data(): void
    {
        $trialEndsAt = Carbon::now()->addDays(3);
        $notification = new TrialEndingNotification('Empresa', $trialEndsAt);

        $reflection = new \ReflectionClass($notification);

        $this->assertSame('Empresa', $reflection->getProperty('tenantName')->getValue($notification));
        $this->assertTrue($reflection->getProperty('trialEndsAt')->getValue($notification)->eq($trialEndsAt));
    }

    // -----------------------------------------------------------------
    // PaymentRequiresActionNotification
    // -----------------------------------------------------------------

    public function test_payment_requires_action_existe(): void
    {
        $reflection = new \ReflectionClass(PaymentRequiresActionNotification::class);

        $this->assertTrue($reflection->hasMethod('via'));
        $this->assertTrue($reflection->hasMethod('toMail'));
    }

    public function test_payment_requires_action_via_mail(): void
    {
        // Requer objeto Payment — testamos apenas a existência do método
        $notification = new PaymentRequiresActionNotification(
            new \Laravel\Cashier\Payment(new \Stripe\PaymentIntent())
        );

        $this->assertContains('mail', $notification->via(new \stdClass()));
    }

    // -----------------------------------------------------------------
    // AbandonedCheckoutNotification
    // -----------------------------------------------------------------

    public function test_abandoned_checkout_via_mail(): void
    {
        $notification = new AbandonedCheckoutNotification('Empresa', 'master', 'https://checkout.stripe.com/session');

        $this->assertContains('mail', $notification->via(new \stdClass()));
    }

    public function test_abandoned_checkout_aceita_parametros(): void
    {
        $notification = new AbandonedCheckoutNotification('Empresa', 'master', 'https://checkout.stripe.com/session');

        $reflection = new \ReflectionClass($notification);

        $this->assertSame('Empresa', $reflection->getProperty('tenantName')->getValue($notification));
        $this->assertSame('master', $reflection->getProperty('planSlug')->getValue($notification));
        $this->assertSame('https://checkout.stripe.com/session', $reflection->getProperty('signupUrl')->getValue($notification));
    }

    // -----------------------------------------------------------------
    // TenantResetPasswordNotification
    // -----------------------------------------------------------------

    public function test_reset_password_notification_existe(): void
    {
        $reflection = new \ReflectionClass(TenantResetPasswordNotification::class);

        $this->assertTrue($reflection->hasMethod('toMail'));
    }

    public function test_reset_password_notification_via_mail(): void
    {
        $notification = new TenantResetPasswordNotification('https://app.sigapp.com.br/reset?token=abc', 60);

        $this->assertContains('mail', $notification->via(new \stdClass()));
    }
}
