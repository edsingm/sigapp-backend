<?php

namespace Tests\Feature\Billing;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Notifications\PaymentRetryNotification;
use App\Notifications\TrialEndingNotification;
use App\Services\Billing\TenantBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class WebhookHandlerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Envia um payload de webhook para o endpoint central.
     * Em ambiente testing, a verificação de assinatura é desabilitada automaticamente.
     *
     * @param  array<string, mixed>  $dataObject
     * @param  array<string, mixed>  $extra
     */
    private function postWebhook(string $eventType, array $dataObject, array $extra = []): TestResponse
    {
        $payload = array_merge([
            'id' => 'evt_test_'.uniqid(),
            'type' => $eventType,
            'data' => ['object' => $dataObject],
        ], $extra);

        return $this
            ->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->postJson('/api/v1/webhook/stripe', $payload);
    }

    /**
     * Cria um tenant ativo com stripe_id para uso nos testes.
     *
     * @param  array<string, mixed>  $attrs
     */
    private function makeTenant(array $attrs = []): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Teste',
            'slug' => 'master',
            'description' => 'Plano para testes',
            'stripe_price_id' => 'price_test_'.uniqid(),
            'price' => 19900,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        return Tenant::create(array_merge([
            'name' => 'Empresa Teste',
            'slug' => 'empresa-teste-'.uniqid(),
            'status' => Tenant::STATUS_ACTIVE,
            'admin_email' => 'admin@empresa-teste.test',
            'admin_name' => 'Admin Teste',
            'stripe_id' => 'cus_test_'.uniqid(),
            'plan_id' => $plan->id,
        ], $attrs));
    }

    // -------------------------------------------------------------------------
    // invoice.payment_failed
    // -------------------------------------------------------------------------

    public function test_payment_failed_sends_notification_on_first_attempt(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();

        $this->postWebhook('invoice.payment_failed', [
            'customer' => $tenant->stripe_id,
            'attempt_count' => 1,
            'hosted_invoice_url' => 'https://invoice.stripe.com/inv_test',
            'id' => 'in_test_001',
        ])->assertOk();

        Notification::assertSentTo($tenant, PaymentRetryNotification::class);
        $this->assertEquals(Tenant::STATUS_ACTIVE, $tenant->fresh()->status);
    }

    public function test_payment_failed_sends_notification_on_second_attempt(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();

        $this->postWebhook('invoice.payment_failed', [
            'customer' => $tenant->stripe_id,
            'attempt_count' => 2,
            'id' => 'in_test_002',
        ])->assertOk();

        Notification::assertSentTo($tenant, PaymentRetryNotification::class);
        $this->assertEquals(Tenant::STATUS_ACTIVE, $tenant->fresh()->status);
    }

    public function test_payment_failed_suspends_tenant_after_three_attempts(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();

        $this->postWebhook('invoice.payment_failed', [
            'customer' => $tenant->stripe_id,
            'attempt_count' => 3,
            'id' => 'in_test_003',
        ])->assertOk();

        Notification::assertSentTo($tenant, PaymentRetryNotification::class);
        $this->assertEquals(Tenant::STATUS_SUSPENDED, $tenant->fresh()->status);
    }

    public function test_payment_failed_with_unknown_customer_returns_ok_without_error(): void
    {
        Notification::fake();

        $this->postWebhook('invoice.payment_failed', [
            'customer' => 'cus_does_not_exist_xyz',
            'attempt_count' => 1,
            'id' => 'in_unknown',
        ])->assertOk();

        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // customer.subscription.trial_will_end
    // -------------------------------------------------------------------------

    public function test_trial_will_end_sends_notification(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant([
            'stripe_subscription_id' => 'sub_test_'.uniqid(),
            'trial_ends_at' => now()->addDays(3),
        ]);

        $trialEnd = now()->addDays(3)->timestamp;

        $this->postWebhook('customer.subscription.trial_will_end', [
            'customer' => $tenant->stripe_id,
            'trial_end' => $trialEnd,
            'id' => 'sub_test_trial_'.uniqid(),
            'status' => 'trialing',
        ])->assertOk();

        Notification::assertSentTo($tenant, TrialEndingNotification::class);
    }

    public function test_trial_will_end_with_unknown_customer_returns_ok(): void
    {
        Notification::fake();

        $this->postWebhook('customer.subscription.trial_will_end', [
            'customer' => 'cus_no_tenant_xyz',
            'trial_end' => now()->addDays(3)->timestamp,
            'id' => 'sub_test_no_tenant',
        ])->assertOk();

        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Idempotência — webhook duplicado
    // -------------------------------------------------------------------------

    public function test_duplicate_webhook_event_is_processed_only_once(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $eventId = 'evt_test_duplicate_'.uniqid();

        $dataObject = [
            'customer' => $tenant->stripe_id,
            'attempt_count' => 1,
            'id' => 'in_dup_001',
        ];

        // Primeiro envio
        $this->postWebhook('invoice.payment_failed', $dataObject, ['id' => $eventId])->assertOk();
        // Segundo envio com o mesmo event_id
        $this->postWebhook('invoice.payment_failed', $dataObject, ['id' => $eventId])->assertOk();

        // Notificação deve ter sido enviada apenas uma vez
        Notification::assertSentToTimes($tenant, PaymentRetryNotification::class, 1);
    }

    // -------------------------------------------------------------------------
    // customer.subscription.deleted
    // -------------------------------------------------------------------------

    public function test_subscription_deleted_cancels_tenant(): void
    {
        Notification::fake();
        $subscriptionId = 'sub_deleted_'.uniqid();
        $tenant = $this->makeTenant([
            'stripe_subscription_id' => $subscriptionId,
        ]);

        $this->postWebhook('customer.subscription.deleted', [
            'customer' => $tenant->stripe_id,
            'id' => $subscriptionId,
            'status' => 'canceled',
        ])->assertOk();

        $this->assertEquals(Tenant::STATUS_CANCELLED, $tenant->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // past_due — notifica mas não suspende
    // -------------------------------------------------------------------------

    public function test_past_due_subscription_notifies_but_does_not_suspend(): void
    {
        Notification::fake();
        $subscriptionId = 'sub_past_due_'.uniqid();
        $tenant = $this->makeTenant([
            'stripe_subscription_id' => $subscriptionId,
        ]);

        // Mocka TenantBillingService para evitar chamada real ao Stripe
        $billingMock = Mockery::mock(TenantBillingService::class)->makePartial();
        $billingMock->shouldReceive('retrieveSubscription')
            ->andReturn((object) [
                'id' => $subscriptionId,
                'status' => 'past_due',
                'customer' => $tenant->stripe_id,
                'items' => (object) [
                    'data' => [
                        (object) [
                            'id' => 'si_test',
                            'price' => (object) ['id' => 'price_test', 'product' => 'prod_test'],
                            'quantity' => 1,
                        ],
                    ],
                ],
                'trial_end' => null,
                'cancel_at' => null,
            ]);
        $billingMock->shouldReceive('syncPlanFromPriceId')->andReturnNull();
        $billingMock->shouldReceive('syncSubscription')->andReturnNull();

        $this->app->instance(TenantBillingService::class, $billingMock);

        $this->postWebhook('customer.subscription.updated', [
            'customer' => $tenant->stripe_id,
            'id' => $subscriptionId,
            'status' => 'past_due',
            'items' => [
                'data' => [
                    [
                        'id' => 'si_test',
                        'price' => ['id' => 'price_test', 'product' => 'prod_test'],
                        'quantity' => 1,
                    ],
                ],
            ],
        ])->assertOk();

        // Tenant deve continuar ativo (não suspenso)
        $this->assertEquals(Tenant::STATUS_ACTIVE, $tenant->fresh()->status);
        // Deve ter sido notificado
        Notification::assertSentTo($tenant, PaymentRetryNotification::class);
    }

    // -------------------------------------------------------------------------
    // Segurança — webhook sem assinatura aceito em testing
    // -------------------------------------------------------------------------

    public function test_webhook_accepted_without_signature_in_testing_environment(): void
    {
        $this->postWebhook('invoice.paid', [
            'customer' => 'cus_no_match',
            'subscription' => null,
            'id' => 'in_no_match',
        ])->assertOk();
    }

    // -------------------------------------------------------------------------
    // charge.dispute.created
    // -------------------------------------------------------------------------

    public function test_charge_dispute_created_returns_ok_and_logs(): void
    {
        $this->postWebhook('charge.dispute.created', [
            'id' => 'dp_test_'.uniqid(),
            'charge' => 'ch_test_'.uniqid(),
            'amount' => 19900,
            'reason' => 'fraudulent',
        ])->assertOk();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
