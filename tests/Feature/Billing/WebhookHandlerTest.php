<?php

namespace Tests\Feature\Billing;

use App\Models\Central\Coupon;
use App\Models\Central\Dispute;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Notifications\DisputeCreatedNotification;
use App\Notifications\PaymentActionRequiredNotification;
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

    private function assertTenantStatus(Tenant $tenant, string $expectedStatus): void
    {
        $freshTenant = $tenant->fresh();

        if (! $freshTenant instanceof Tenant) {
            $this->fail('Tenant not found after webhook processing.');
        }

        $this->assertSame($expectedStatus, $freshTenant->status);
    }

    // -------------------------------------------------------------------------
    // invoice.payment_failed
    // -------------------------------------------------------------------------

    public function test_payment_failed_sends_notification_on_first_attempt(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();

        $this->postWebhook('invoice.payment_failed', [
            'customer' => $tenant->getAttribute('stripe_id'),
            'attempt_count' => 1,
            'hosted_invoice_url' => 'https://invoice.stripe.com/inv_test',
            'id' => 'in_test_001',
        ])->assertOk();

        Notification::assertSentTo($tenant, PaymentRetryNotification::class);
        $this->assertTenantStatus($tenant, Tenant::STATUS_ACTIVE);
    }

    public function test_payment_failed_sends_notification_on_second_attempt(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();

        $this->postWebhook('invoice.payment_failed', [
            'customer' => $tenant->getAttribute('stripe_id'),
            'attempt_count' => 2,
            'id' => 'in_test_002',
        ])->assertOk();

        Notification::assertSentTo($tenant, PaymentRetryNotification::class);
        $this->assertTenantStatus($tenant, Tenant::STATUS_ACTIVE);
    }

    public function test_payment_failed_suspends_tenant_after_three_attempts(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();

        $this->postWebhook('invoice.payment_failed', [
            'customer' => $tenant->getAttribute('stripe_id'),
            'attempt_count' => 3,
            'id' => 'in_test_003',
        ])->assertOk();

        Notification::assertSentTo($tenant, PaymentRetryNotification::class);
        $this->assertTenantStatus($tenant, Tenant::STATUS_SUSPENDED);
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

    public function test_payment_action_required_sends_specific_notification_without_suspending(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();

        $this->postWebhook('invoice.payment_action_required', [
            'customer' => $tenant->getAttribute('stripe_id'),
            'hosted_invoice_url' => 'https://invoice.stripe.com/inv_action_required',
            'id' => 'in_action_required_001',
        ])->assertOk();

        Notification::assertSentTo($tenant, PaymentActionRequiredNotification::class);
        Notification::assertNotSentTo($tenant, PaymentRetryNotification::class);
        $this->assertTenantStatus($tenant, Tenant::STATUS_ACTIVE);
    }

    public function test_payment_action_required_with_unknown_customer_returns_ok_without_error(): void
    {
        Notification::fake();

        $this->postWebhook('invoice.payment_action_required', [
            'customer' => 'cus_action_required_unknown',
            'hosted_invoice_url' => 'https://invoice.stripe.com/inv_action_required_unknown',
            'id' => 'in_action_required_unknown',
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
            'customer' => $tenant->getAttribute('stripe_id'),
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
            'customer' => $tenant->getAttribute('stripe_id'),
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

    public function test_duplicate_payment_action_required_event_is_processed_only_once(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $eventId = 'evt_test_action_required_duplicate_'.uniqid();

        $dataObject = [
            'customer' => $tenant->getAttribute('stripe_id'),
            'hosted_invoice_url' => 'https://invoice.stripe.com/inv_action_required_dup',
            'id' => 'in_action_required_dup_001',
        ];

        $this->postWebhook('invoice.payment_action_required', $dataObject, ['id' => $eventId])->assertOk();
        $this->postWebhook('invoice.payment_action_required', $dataObject, ['id' => $eventId])->assertOk();

        Notification::assertSentToTimes($tenant, PaymentActionRequiredNotification::class, 1);
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
            'customer' => $tenant->getAttribute('stripe_id'),
            'id' => $subscriptionId,
            'status' => 'canceled',
        ])->assertOk();

        $this->assertTenantStatus($tenant, Tenant::STATUS_CANCELLED);
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
                'customer' => $tenant->getAttribute('stripe_id'),
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
            'customer' => $tenant->getAttribute('stripe_id'),
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
        $this->assertTenantStatus($tenant, Tenant::STATUS_ACTIVE);
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
    // charge.dispute.created / updated / closed
    // -------------------------------------------------------------------------

    private function mockBillingWithCharge(string $chargeId, string $customerId): void
    {
        $billingMock = Mockery::mock(TenantBillingService::class)->makePartial();
        $billingMock->shouldReceive('retrieveCharge')
            ->with($chargeId)
            ->andReturn((object) ['id' => $chargeId, 'customer' => $customerId]);

        $this->app->instance(TenantBillingService::class, $billingMock);
    }

    public function test_charge_dispute_created_places_tenant_under_review(): void
    {
        Notification::fake();

        $chargeId = 'ch_test_'.uniqid();
        $disputeId = 'dp_test_'.uniqid();
        $tenant = $this->makeTenant();

        $this->mockBillingWithCharge($chargeId, (string) $tenant->getAttribute('stripe_id'));

        $this->postWebhook('charge.dispute.created', [
            'id' => $disputeId,
            'charge' => $chargeId,
            'amount' => 19900,
            'reason' => 'fraudulent',
        ])->assertOk();

        $this->assertTenantStatus($tenant, Tenant::STATUS_UNDER_REVIEW);
        $this->assertDatabaseHas('disputes', [
            'stripe_dispute_id' => $disputeId,
            'stripe_charge_id' => $chargeId,
            'tenant_id' => $tenant->id,
            'status' => 'needs_response',
        ]);
        Notification::assertSentTo($tenant, DisputeCreatedNotification::class);
    }

    public function test_charge_dispute_created_is_idempotent(): void
    {
        Notification::fake();

        $chargeId = 'ch_idem_'.uniqid();
        $disputeId = 'dp_idem_'.uniqid();
        $tenant = $this->makeTenant();

        $this->mockBillingWithCharge($chargeId, (string) $tenant->getAttribute('stripe_id'));

        $this->postWebhook('charge.dispute.created', [
            'id' => $disputeId,
            'charge' => $chargeId,
            'amount' => 19900,
            'reason' => 'fraudulent',
        ])->assertOk();

        // Segundo dispatch com mesmo dispute_id (idempotência do evento, diferente do lock de webhook)
        $this->postWebhook('charge.dispute.created', [
            'id' => $disputeId,
            'charge' => $chargeId,
            'amount' => 19900,
            'reason' => 'fraudulent',
        ])->assertOk();

        $this->assertSame(1, Dispute::where('stripe_dispute_id', $disputeId)->count());
        Notification::assertSentToTimes($tenant, DisputeCreatedNotification::class, 1);
    }

    public function test_charge_dispute_created_with_unknown_customer_returns_ok(): void
    {
        Notification::fake();

        $chargeId = 'ch_unknown_'.uniqid();
        $disputeId = 'dp_unknown_'.uniqid();

        $billingMock = Mockery::mock(TenantBillingService::class)->makePartial();
        $billingMock->shouldReceive('retrieveCharge')
            ->andReturn((object) ['id' => $chargeId, 'customer' => 'cus_does_not_exist']);

        $this->app->instance(TenantBillingService::class, $billingMock);

        $this->postWebhook('charge.dispute.created', [
            'id' => $disputeId,
            'charge' => $chargeId,
            'amount' => 5000,
            'reason' => 'fraudulent',
        ])->assertOk();

        $this->assertDatabaseMissing('disputes', ['stripe_dispute_id' => $disputeId]);
        Notification::assertNothingSent();
    }

    public function test_charge_dispute_updated_updates_local_status(): void
    {
        $tenant = $this->makeTenant();
        $disputeId = 'dp_upd_'.uniqid();

        Dispute::create([
            'tenant_id' => $tenant->id,
            'stripe_dispute_id' => $disputeId,
            'stripe_charge_id' => 'ch_upd_'.uniqid(),
            'amount' => 19900,
            'reason' => 'fraudulent',
            'status' => 'needs_response',
        ]);

        $this->postWebhook('charge.dispute.updated', [
            'id' => $disputeId,
            'charge' => 'ch_upd_test',
            'status' => 'under_review',
        ])->assertOk();

        $this->assertDatabaseHas('disputes', [
            'stripe_dispute_id' => $disputeId,
            'status' => 'under_review',
        ]);
        // Tenant não muda de estado em dispute.updated
        $this->assertTenantStatus($tenant, Tenant::STATUS_ACTIVE);
    }

    public function test_charge_dispute_closed_won_restores_active(): void
    {
        $tenant = $this->makeTenant(['status' => Tenant::STATUS_UNDER_REVIEW]);
        $disputeId = 'dp_won_'.uniqid();

        Dispute::create([
            'tenant_id' => $tenant->id,
            'stripe_dispute_id' => $disputeId,
            'stripe_charge_id' => 'ch_won_'.uniqid(),
            'amount' => 19900,
            'reason' => 'fraudulent',
            'status' => 'under_review',
        ]);

        $this->postWebhook('charge.dispute.closed', [
            'id' => $disputeId,
            'charge' => 'ch_won_test',
            'status' => 'won',
        ])->assertOk();

        $this->assertTenantStatus($tenant, Tenant::STATUS_ACTIVE);
        $this->assertDatabaseHas('disputes', [
            'stripe_dispute_id' => $disputeId,
            'status' => 'won',
        ]);
        $this->assertNotNull(Dispute::where('stripe_dispute_id', $disputeId)->value('resolved_at'));
    }

    public function test_charge_dispute_closed_lost_suspends_tenant(): void
    {
        $tenant = $this->makeTenant(['status' => Tenant::STATUS_UNDER_REVIEW]);
        $disputeId = 'dp_lost_'.uniqid();

        Dispute::create([
            'tenant_id' => $tenant->id,
            'stripe_dispute_id' => $disputeId,
            'stripe_charge_id' => 'ch_lost_'.uniqid(),
            'amount' => 19900,
            'reason' => 'fraudulent',
            'status' => 'under_review',
        ]);

        $this->postWebhook('charge.dispute.closed', [
            'id' => $disputeId,
            'charge' => 'ch_lost_test',
            'status' => 'lost',
        ])->assertOk();

        $this->assertTenantStatus($tenant, Tenant::STATUS_SUSPENDED);
        $this->assertDatabaseHas('disputes', [
            'stripe_dispute_id' => $disputeId,
            'status' => 'lost',
        ]);
    }

    // -------------------------------------------------------------------------
    // Downgrade diferido — customer.subscription.updated
    // -------------------------------------------------------------------------

    public function test_subscription_updated_with_pending_downgrade_skips_plan_sync(): void
    {
        $subscriptionId = 'sub_downgrade_'.uniqid();

        // Cria um plano extra para usar como scheduled_plan_id
        $scheduledPlan = Plan::create([
            'name' => 'Plano Agendado',
            'slug' => 'agendado-'.uniqid(),
            'description' => 'Teste downgrade',
            'stripe_price_id' => 'price_scheduled',
            'price' => 9900,
            'trial_days' => 0,
            'is_active' => true,
        ]);

        $tenant = $this->makeTenant([
            'stripe_subscription_id' => $subscriptionId,
            'scheduled_plan_id' => $scheduledPlan->id,
            'database_created' => true, // evita dispatch de provisioning em teste
        ]);

        $billingMock = Mockery::mock(TenantBillingService::class)->makePartial();
        $billingMock->shouldReceive('retrieveSubscription')
            ->andReturn((object) [
                'id' => $subscriptionId,
                'status' => 'active',
                'customer' => $tenant->getAttribute('stripe_id'),
                'items' => (object) [
                    'data' => [
                        (object) [
                            'id' => 'si_test',
                            'price' => (object) ['id' => 'price_scheduled', 'product' => 'prod_test'],
                            'quantity' => 1,
                        ],
                    ],
                ],
                'trial_end' => null,
                'cancel_at' => null,
            ]);

        // syncPlanFromPriceId NÃO deve ser chamado enquanto o downgrade está pendente
        $billingMock->shouldReceive('syncPlanFromPriceId')->never();
        $billingMock->shouldReceive('syncSubscription')->once()->andReturnNull();
        $billingMock->shouldReceive('applyStripeSubscriptionStatus')->andReturn('active');

        $this->app->instance(TenantBillingService::class, $billingMock);

        $this->postWebhook('customer.subscription.updated', [
            'customer' => $tenant->getAttribute('stripe_id'),
            'id' => $subscriptionId,
            'status' => 'active',
            'items' => [
                'data' => [
                    [
                        'id' => 'si_test',
                        'price' => ['id' => 'price_scheduled', 'product' => 'prod_test'],
                        'quantity' => 1,
                    ],
                ],
            ],
        ])->assertOk();
    }

    // -------------------------------------------------------------------------
    // Downgrade diferido — invoice.paid
    // -------------------------------------------------------------------------

    public function test_invoice_paid_clears_scheduled_plan_id_after_renewal(): void
    {
        $subscriptionId = 'sub_renewal_'.uniqid();
        $scheduledPlan = Plan::create([
            'name' => 'Plano Renovação',
            'slug' => 'renovacao-'.uniqid(),
            'description' => 'Teste',
            'stripe_price_id' => 'price_renewed',
            'price' => 9900,
            'trial_days' => 0,
            'is_active' => true,
        ]);

        $tenant = $this->makeTenant([
            'stripe_subscription_id' => $subscriptionId,
            'scheduled_plan_id' => $scheduledPlan->id,
            'database_created' => true, // evita dispatch de provisioning em teste
        ]);

        $billingMock = Mockery::mock(TenantBillingService::class)->makePartial();
        $billingMock->shouldReceive('retrieveSubscription')
            ->andReturn((object) [
                'id' => $subscriptionId,
                'status' => 'active',
                'customer' => $tenant->getAttribute('stripe_id'),
                'items' => (object) [
                    'data' => [
                        (object) [
                            'id' => 'si_test',
                            'price' => (object) ['id' => 'price_renewed', 'product' => 'prod_test'],
                            'quantity' => 1,
                        ],
                    ],
                ],
                'trial_end' => null,
                'cancel_at' => null,
            ]);

        // No invoice.paid, syncPlanFromPriceId DEVE rodar normalmente
        $billingMock->shouldReceive('syncPlanFromPriceId')->once()->andReturnNull();
        $billingMock->shouldReceive('syncSubscription')->once()->andReturnNull();
        $billingMock->shouldReceive('applyStripeSubscriptionStatus')->andReturn('active');

        $this->app->instance(TenantBillingService::class, $billingMock);

        $this->postWebhook('invoice.paid', [
            'customer' => $tenant->getAttribute('stripe_id'),
            'subscription' => $subscriptionId,
            'id' => 'in_renewal_'.uniqid(),
        ])->assertOk();

        // scheduled_plan_id deve ser limpo após a renovação confirmada pelo Stripe
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'scheduled_plan_id' => null,
        ]);
    }

    public function test_invoice_paid_without_pending_downgrade_does_not_fail(): void
    {
        $subscriptionId = 'sub_normal_'.uniqid();
        $tenant = $this->makeTenant([
            'stripe_subscription_id' => $subscriptionId,
            'database_created' => true,
            // scheduled_plan_id = null (sem downgrade pendente)
        ]);

        $billingMock = Mockery::mock(TenantBillingService::class)->makePartial();
        $billingMock->shouldReceive('retrieveSubscription')
            ->andReturn((object) [
                'id' => $subscriptionId,
                'status' => 'active',
                'customer' => $tenant->getAttribute('stripe_id'),
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
        $billingMock->shouldReceive('syncPlanFromPriceId')->once()->andReturnNull();
        $billingMock->shouldReceive('syncSubscription')->once()->andReturnNull();
        $billingMock->shouldReceive('applyStripeSubscriptionStatus')->andReturn('active');

        $this->app->instance(TenantBillingService::class, $billingMock);

        $this->postWebhook('invoice.paid', [
            'customer' => $tenant->getAttribute('stripe_id'),
            'subscription' => $subscriptionId,
            'id' => 'in_normal_'.uniqid(),
        ])->assertOk();

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'scheduled_plan_id' => null,
        ]);
    }

    public function test_invoice_paid_reads_subscription_id_from_basil_parent_field(): void
    {
        $subscriptionId = 'sub_basil_'.uniqid();
        $scheduledPlan = Plan::create([
            'name' => 'Plano Basil',
            'slug' => 'basil-'.uniqid(),
            'description' => 'Teste Basil',
            'stripe_price_id' => 'price_basil',
            'price' => 9900,
            'trial_days' => 0,
            'is_active' => true,
        ]);

        $tenant = $this->makeTenant([
            'stripe_subscription_id' => $subscriptionId,
            'scheduled_plan_id' => $scheduledPlan->id,
            'database_created' => true, // evita dispatch de provisioning em teste
        ]);

        $billingMock = Mockery::mock(TenantBillingService::class)->makePartial();
        $billingMock->shouldReceive('retrieveSubscription')
            ->with($subscriptionId)
            ->andReturn((object) [
                'id' => $subscriptionId,
                'status' => 'active',
                'customer' => $tenant->getAttribute('stripe_id'),
                'items' => (object) [
                    'data' => [
                        (object) [
                            'id' => 'si_test',
                            'price' => (object) ['id' => 'price_basil', 'product' => 'prod_test'],
                            'quantity' => 1,
                        ],
                    ],
                ],
                'trial_end' => null,
                'cancel_at' => null,
            ]);
        $billingMock->shouldReceive('syncPlanFromPriceId')->once()->andReturnNull();
        $billingMock->shouldReceive('syncSubscription')->once()->andReturnNull();
        $billingMock->shouldReceive('applyStripeSubscriptionStatus')->andReturn('active');

        $this->app->instance(TenantBillingService::class, $billingMock);

        // Payload Basil/Clover: sem campo legado 'subscription', ID em parent.subscription_details.
        $this->postWebhook('invoice.paid', [
            'customer' => $tenant->getAttribute('stripe_id'),
            'id' => 'in_basil_'.uniqid(),
            'parent' => [
                'subscription_details' => ['subscription' => $subscriptionId],
            ],
        ])->assertOk();

        // Reconciliação deve ter ocorrido mesmo sem o campo legado.
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'scheduled_plan_id' => null,
        ]);
    }

    public function test_customer_discount_created_increments_redemption_once(): void
    {
        $coupon = Coupon::create([
            'stripe_coupon_id' => 'disc_test',
            'code' => 'PROMO',
            'name' => 'Promo',
            'type' => 'percent',
            'percent_off' => 10,
            'is_active' => true,
        ]);

        $eventId = 'evt_discount_'.uniqid();
        // No customer.discount.created, data.object é um Discount; o cupom fica em coupon.id.
        $dataObject = [
            'id' => 'di_test',
            'customer' => 'cus_test',
            'coupon' => ['id' => 'disc_test'],
        ];

        $this->postWebhook('customer.discount.created', $dataObject, ['id' => $eventId])->assertOk();
        // Reenvio do mesmo event id não deve recontar (idempotência via webhook_events).
        $this->postWebhook('customer.discount.created', $dataObject, ['id' => $eventId])->assertOk();

        $this->assertSame(1, $coupon->fresh()->times_redeemed);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
