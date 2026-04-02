Context
O review completo da integração Stripe/Cashier identificou 19 problemas distribuídos em três prioridades (crítico, importante, melhoria). Este plano implementa todos eles de forma sequencial, respeitando dependências entre arquivos. A base é sólida — idempotência de webhooks, auditoria, reconciliação — o foco é em notificações faltantes, métodos de pagamento brasileiros, upgrade/downgrade inline e cobertura de testes.

Ordem de Implementação (22 passos)
FASE 0 — Config e Migration (sem dependências)
Passo 1 — config/cashier.php

Linha 111: 'paper' => env('CASHIER_PAPER', 'A4') (era 'letter')

Passo 2 — .env.example

Adicionar: CASHIER_PAYMENT_NOTIFICATION=App\Notifications\PaymentRequiresActionNotification

Passo 3 — Nova migration: database/migrations/2026_04_02_000001_drop_cashier_columns_from_users_table.php

Drop: stripe_id (com seu index), pm_type, pm_last_four, trial_ends_at da tabela users
Motivo: Billable é Tenant, não User — colunas nunca são populadas
Verificar antes: grep -rn "pm_type\|pm_last_four" app/ --include="*.php" para confirmar que não há uso


FASE 1 — Notifications (base para os handlers)
Passo 4 — Criar app/Notifications/PaymentFailedNotification.php

Padrão: igual TenantWelcomeNotification (Queueable, via mail, toMail() em PT-BR)
Constructor: string $tenantName, int $attemptCount, ?string $invoiceUrl
Conteúdo: informa a tentativa, mostra botão "Pagar fatura" se $invoiceUrl existir, pede atualização do método de pagamento

Passo 5 — Criar app/Notifications/PaymentRequiresActionNotification.php

Constructor: Laravel\Cashier\Payment $payment
Conteúdo: informa 3DS/SCA requerido, botão "Confirmar pagamento" → $payment->url
Esta classe é usada pelo CASHIER_PAYMENT_NOTIFICATION do Cashier (Cashier chama $tenant->notify(new $class($payment)))

Passo 6 — Criar app/Notifications/TrialEndingNotification.php

Constructor: string $tenantName, Carbon $trialEndsAt
Conteúdo: avisa que trial termina em X dias, botão "Gerenciar assinatura" → {frontend_url}/billing

Passo 7 — Criar app/Notifications/AbandonedCheckoutNotification.php

Constructor: string $tenantName, string $planSlug, ?string $signupUrl
Conteúdo: informa que conta foi removida por inatividade, convida a novo cadastro


FASE 2 — WebhookController (depende das Notifications)
Arquivo: app/Http/Controllers/Api/V1/WebhookController.php
Passo 8 — Reescrever handleInvoicePaymentFailed() (resolve Issues #1 e #9):
phpprotected function handleInvoicePaymentFailed(array $payload)
{
    $invoice    = (array) data_get($payload, 'data.object', []);  // era (object) cast direto
    $customerId = data_get($invoice, 'customer');
    $attempts   = (int) data_get($invoice, 'attempt_count', 0);
    $invoiceUrl = data_get($invoice, 'hosted_invoice_url');

    $tenant = Tenant::where('stripe_id', $customerId)->first();
    if (! $tenant) return $this->successMethod();

    // Notifica em TODA falha (novo)
    $tenant->notify(new PaymentFailedNotification($tenant->name, $attempts, $invoiceUrl));
    $this->audit('tenant.payment_notification_sent', "Notificação de falha enviada (tentativa {$attempts}).", [...]);

    // Suspende após 3 tentativas (comportamento existente mantido)
    if ($attempts >= 3) {
        $tenant->suspend();
        $this->audit('tenant.payment_failed', "Tenant suspenso após {$attempts} tentativas.", [...]);
    }

    return $this->successMethod();
}
Passo 9 — Adicionar handleCustomerSubscriptionTrialWillEnd() (resolve Issue #8):

Cashier roteia automaticamente pelo nome StudlyCase
Buscar tenant por stripe_id, enviar TrialEndingNotification, registrar audit
Registrar customer.subscription.trial_will_end no Dashboard do Stripe

Passo 10 — Adicionar handleChargeDisputeCreated() (resolve Issue #16):

Log::critical com dados do dispute (charge_id, amount, reason)
Audit stripe.dispute_created
Registrar charge.dispute.created no Dashboard do Stripe

Passo 11 — Remover bloco redundante em handleCheckoutSessionCompleted() (resolve Issue #18):

Remover as linhas 162–165 (o $tenant->update(['stripe_subscription_id' => ..., 'stripe_id' => ...]))
reconcileTenantBillingState() já faz isso com dados diretamente do Stripe

Passo 12 — Adicionar guard para Boleto em handleCheckoutSessionCompleted() (resolve Issue #3 parcial):

Após a validação, antes de dispatchTenantProvisioning:

php$paymentStatus = data_get($session, 'payment_status');
if ($paymentStatus !== 'paid' && $paymentStatus !== 'no_payment_required') {
    Log::info('Checkout concluído mas pagamento pendente (método assíncrono)', [...]);
    // Provisionamento será disparado via invoice.paid
    return $this->successMethod();
}

Isso garante que Boleto (que gera checkout mas não paga imediatamente) não provisiona o tenant antes do pagamento real


FASE 3 — TenantBillingService + StripeCheckoutService
Passo 13 — app/Services/Billing/TenantBillingService.php: tratar past_due (resolve Issue #2):
php'past_due' => tap(self::STATUS_NOOP, fn () => $tenant->notify(
    new PaymentFailedNotification($tenant->name, 0, null)
)),

Estratégia: notifica mas NÃO suspende. O Stripe está ativo com retries; invoice.payment_failed trata a suspensão progressiva. past_due apenas alerta o usuário.

Passo 14 — app/Services/Billing/TenantBillingService.php: sincronizar trial_ends_at do Stripe (resolve Issue #7):

Em syncSubscription(), após salvar o registro de subscription:

phpif ($stripeSubscription->trial_end) {
    $trialEndsAt = Carbon::createFromTimestamp($stripeSubscription->trial_end);
    if (! $tenant->trial_ends_at || ! $tenant->trial_ends_at->eq($trialEndsAt)) {
        $tenant->update(['trial_ends_at' => $trialEndsAt]);
    }
} elseif ($tenant->trial_ends_at && $stripeSubscription->status !== 'trialing') {
    $tenant->update(['trial_ends_at' => null]);
}
Passo 15 — app/Services/Billing/StripeCheckoutService.php: remover payment_method_types (resolve Issue #3):

Remover a chave 'payment_method_types' => ['card'] completamente do array de createSubscriptionSession()
Quando omitida, o Stripe Checkout usa os métodos habilitados no Dashboard automaticamente (Boleto, Pix, cartão)
Adicionar também: 'allow_promotion_codes' => true (resolve Issue #14)
Adicionar também: 'tax_id_collection' => ['enabled' => true] e 'customer_update' => ['name' => 'auto', 'address' => 'auto'] (resolve Issue #15)

Passo 16 — app/Services/Billing/StripeCheckoutService.php: idempotency em createPriceOnTheFly() (resolve Issue #4):
php$idBase = 'plan-' . $plan->id . '-' . $plan->slug;
$product = Cashier::stripe()->products->create(
    ['name' => $plan->name, 'description' => $plan->description],
    ['idempotency_key' => 'product-' . $idBase]
);
$price = Cashier::stripe()->prices->create(
    [...],
    ['idempotency_key' => 'price-' . $idBase . '-' . $plan->price]
);

FASE 4 — Novas Rotas e Controllers
Passo 17 — routes/api.php: rate limiter para signup status (resolve Issue #17):

Adicionar RateLimiter::for('signup-status', ...) — 30 req/min por IP+sessionId (sha1)
Adicionar ->middleware('throttle:signup-status') na rota GET /signup/{sessionId}/status

Passo 18 — Criar app/Http/Controllers/Api/V1/Tenant/PlanSwapController.php (resolve Issue #6):

Método swap(PlanSwapRequest $request, TenantBillingService $billingService)
Validação: plan_slug (exists:plans,slug), prorate (boolean, default true)
Lógica: buscar subscription('default'), verificar se está ativa, chamar swapAndInvoice() (upgrade com prorate=true) ou swap() (downgrade com prorate=false), atualizar plan_id, audit
Retornar PlanResource do novo plano

Passo 19 — Criar app/Http/Requests/Tenant/PlanSwapRequest.php:

plan_slug: required, string, exists:plans,slug
prorate: sometimes, boolean

Passo 20 — routes/tenant.php: novas rotas de billing (resolve Issues #6 e #13):
php// Dentro do grupo auth:sanctum, FORA do CheckSubscriptionStatus (tenant suspenso pode querer reativar)
Route::post('/tenant/subscription/swap', [PlanSwapController::class, 'swap'])
    ->middleware('tenant.admin');
Route::post('/tenant/billing/setup-intent', [TenantController::class, 'createSetupIntent'])
    ->middleware('tenant.admin');
Route::post('/tenant/billing/payment-method', [TenantController::class, 'updateDefaultPaymentMethod'])
    ->middleware('tenant.admin');
Passo 21 — app/Http/Controllers/Api/V1/Tenant/TenantController.php: novos métodos (resolve Issue #13):

createSetupIntent(): $tenant->createSetupIntent() → retornar client_secret
updateDefaultPaymentMethod(Request $request): validar payment_method_id (starts_with:pm_), chamar $tenant->updateDefaultPaymentMethod($pmId)


FASE 5 — CleanupPendingTenantsJob
Passo 22 — app/Jobs/CleanupPendingTenantsJob.php (resolve Issue #19):

Antes da deleção: capturar $adminEmail, $tenantName, $planSlug
Após deleção bem-sucedida: enviar via Notification::route('mail', $adminEmail)->notify(new AbandonedCheckoutNotification(...))
Usar Notification::route() porque o model já foi deletado


FASE 6 — Testes
Passo 23 — Criar tests/Feature/Billing/WebhookHandlerTest.php (resolve Issue #12):
Helper methods:

postWebhook(string $type, array $dataObject, array $extra = []) — POST para /api/v1/webhook/stripe com Host central, sem assinatura (testing bypassa a verificação)
makeTenant(array $attrs = []) — cria tenant com stripe_id e campos obrigatórios

Casos de teste:

test_payment_failed_sends_notification_on_first_attempt — attempt_count=1, notificação enviada, tenant ativo
test_payment_failed_suspends_tenant_after_three_attempts — attempt_count=3, notificação + suspensão
test_payment_failed_with_unknown_customer_returns_ok — cus_not_found, 200 sem erros
test_trial_will_end_sends_notification — TrialEndingNotification enviada
test_duplicate_webhook_is_idempotent — mesmo event_id enviado 2x, notificação enviada 1x
test_past_due_notifies_but_does_not_suspend — mock de TenantBillingService::retrieveSubscription() retornando status past_due, tenant permanece ativo, notificação enviada
test_subscription_deleted_cancels_tenant — customer.subscription.deleted, tenant cancelado
test_invoice_paid_reconciles_tenant_state — invoice.paid, mock do service de reconciliação

Nota sobre mocks no Stripe: Http::fake() NÃO intercepta chamadas do SDK oficial da Stripe (não usa o HTTP client do Laravel). Usar $this->mock(TenantBillingService::class) via container do Laravel para testes que precisam de reconciliação com Stripe.

Arquivos Críticos
ArquivoTipo de mudançaapp/Http/Controllers/Api/V1/WebhookController.phpModificar — 5 mudançasapp/Services/Billing/TenantBillingService.phpModificar — 2 mudançasapp/Services/Billing/StripeCheckoutService.phpModificar — 3 mudançasapp/Jobs/CleanupPendingTenantsJob.phpModificar — 1 mudançaapp/Http/Controllers/Api/V1/Tenant/TenantController.phpModificar — 2 novos métodosconfig/cashier.phpModificar — 1 linha.env.exampleModificar — 1 linharoutes/api.phpModificar — rate limiterroutes/tenant.phpModificar — 3 novas rotasapp/Notifications/PaymentFailedNotification.phpNovoapp/Notifications/PaymentRequiresActionNotification.phpNovoapp/Notifications/TrialEndingNotification.phpNovoapp/Notifications/AbandonedCheckoutNotification.phpNovoapp/Http/Controllers/Api/V1/Tenant/PlanSwapController.phpNovoapp/Http/Requests/Tenant/PlanSwapRequest.phpNovodatabase/migrations/2026_04_02_000001_drop_cashier_columns_from_users_table.phpNovotests/Feature/Billing/WebhookHandlerTest.phpNovo

Verificação / Como Testar
bash# 1. Rodar a suite de testes
php artisan test tests/Feature/Billing/WebhookHandlerTest.php

# 2. Rodar suite completa para confirmar sem regressões
composer test

# 3. Rodar migration no banco de dev
php artisan migrate

# 4. Verificar que colunas foram removidas da tabela users
php artisan tinker --execute="Schema::getColumnListing('users')"

# 5. Testar webhook localmente com Stripe CLI
stripe listen --forward-to localhost:8000/api/v1/webhook/stripe
stripe trigger invoice.payment_failed

# 6. Verificar análise estática (sem erros novos)
./vendor/bin/phpstan analyse app --level=8
Configurações do Stripe Dashboard a registrar:

Adicionar evento customer.subscription.trial_will_end no webhook endpoint
Adicionar evento charge.dispute.created no webhook endpoint
Habilitar métodos de pagamento Boleto e Pix (Settings → Payment Methods → BRL)