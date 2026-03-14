<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SignupRequest;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\ApiResponseService;
use App\Services\Billing\TenantBillingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;
use Stancl\Tenancy\Exceptions\TenantDatabaseAlreadyExistsException;
use Stancl\Tenancy\Database\Models\Domain;

class SignupController extends Controller
{
    public function __construct(
        protected TenantBillingService $billingService
    ) {
    }

    /**
     * Create a new tenant (pending status).
     *
     * POST /api/v1/signup
     */
    public function store(SignupRequest $request)
    {
        $validated = $request->validated();
        $requestedSlug = (string) ($validated['slug'] ?? '');
        $effectiveRequestedSlug = Str::slug($requestedSlug);
        $contractConfig = $this->getSignupUsageContractConfig();

        // Find the plan
        $plan = Plan::where('slug', $validated['plan_slug'])->active()->first();

        if (!$plan) {
            return ApiResponseService::notFound('PLAN_NOT_FOUND');
        }

        try {
            // Use transaction with lock to prevent race condition
            $tenant = DB::transaction(function () use ($validated, $plan, $request, $contractConfig, $requestedSlug, $effectiveRequestedSlug) {
                $existingTenant = Tenant::where('slug', $effectiveRequestedSlug)
                    ->lockForUpdate()
                    ->first();

                $existingDomain = Domain::query()
                    ->where('domain', $effectiveRequestedSlug)
                    ->lockForUpdate()
                    ->first();

                if ($existingTenant || $existingDomain) {
                    $this->audit('tenant.signup_slug_conflict', "Slug '{$effectiveRequestedSlug}' indisponível no cadastro.", [
                        'requested_slug' => $requestedSlug,
                        'effective_slug' => $effectiveRequestedSlug,
                        'admin_email' => $validated['admin_email'],
                    ]);

                    throw ValidationException::withMessages([
                        'slug' => [language()->t('SUBDOMAIN_UNVAVAILABLE')],
                    ]);
                }

                $contractAcceptance = $this->makeSignupContractAcceptancePayload(
                    validated: $validated,
                    contractConfig: $contractConfig,
                    request: $request,
                    requestedSlug: $requestedSlug,
                    effectiveSlug: $effectiveRequestedSlug,
                );

                // Create pending tenant
                $tenant = Tenant::create([
                    'name' => $validated['organization_name'],
                    'slug' => $effectiveRequestedSlug,
                    'status' => Tenant::STATUS_PENDING,
                    'plan_id' => $plan->id,
                    'trial_ends_at' => now()->addDays($plan->trial_days),
                    'admin_name' => $validated['admin_name'],
                    'admin_email' => $validated['admin_email'],
                    'admin_password' => $validated['admin_password'],
                    'data' => [
                        'signup_contract_acceptance' => $contractAcceptance,
                    ],
                ]);

                // Create domain for subdomain identification
                $tenant->domains()->create([
                    'domain' => $tenant->slug,
                ]);

                // Safety: ensure plan_id is persisted on the tenants table
                if (!$tenant->getOriginal('plan_id')) {
                    Tenant::whereKey($tenant->id)->update([
                        'plan_id' => $plan->id,
                    ]);
                    $tenant->refresh();
                }

                return $tenant;
            });

            $contractAcceptance = data_get($tenant->data ?? [], 'signup_contract_acceptance', []);

            $this->audit('tenant.signup_contract_accepted', 'Aceite de contrato de utilização registrado no signup.', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'tenant_name' => $tenant->name,
                'plan_slug' => $plan->slug,
                'admin_email' => $validated['admin_email'],
                'admin_name' => $validated['admin_name'],
                'document_key' => $contractAcceptance['document_key'] ?? null,
                'document_title' => $contractAcceptance['document_title'] ?? null,
                'document_version' => $contractAcceptance['document_version'] ?? null,
                'document_hash' => $contractAcceptance['document_hash'] ?? null,
                'document_url' => $contractAcceptance['document_url'] ?? null,
                'accepted_at' => $contractAcceptance['accepted_at'] ?? null,
                'accepted' => $contractAcceptance['accepted'] ?? false,
                'ip_address' => $contractAcceptance['ip_address'] ?? null,
                'user_agent' => $contractAcceptance['user_agent'] ?? null,
            ]);

            // Audit: tenant signup started
            $this->audit('tenant.signup_started', "Novo tenant '{$tenant->name}' criado (pendente). Aguardando checkout Stripe.", [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'tenant_name' => $tenant->name,
                'plan_slug' => $plan->slug,
                'plan_name' => $plan->name,
                'admin_email' => $validated['admin_email'],
                'admin_name' => $validated['admin_name'],
                'trial_days' => $plan->trial_days,
            ]);

            // Create Stripe customer
            $stripeCustomer = Cashier::stripe()->customers->create([
                'email' => $validated['admin_email'],
                'name' => $validated['organization_name'],
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'tenant_slug' => $tenant->slug,
                ],
            ]);

            $tenant->update(['stripe_id' => $stripeCustomer->id]);

            // Create Stripe Checkout session
            $session = Cashier::stripe()->checkout->sessions->create([
                'customer' => $stripeCustomer->id,
                'client_reference_id' => (string) $tenant->id,
                'payment_method_types' => ['card'],
                'mode' => 'subscription',
                'line_items' => [
                    [
                        'price' => $plan->stripe_price_id ?? $this->createPriceOnTheFly($plan),
                        'quantity' => 1,
                    ],
                ],
                'subscription_data' => [
                    'trial_period_days' => $plan->trial_days,
                    'metadata' => [
                        'tenant_id' => $tenant->id,
                    ],
                ],
                'success_url' => $this->signupSuccessUrl(),
                'cancel_url' => $this->signupCancelUrl($plan->slug),
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'plan_slug' => $plan->slug,
                ],
            ]);

            $tenantData = $tenant->data ?? [];
            data_set($tenantData, 'signup_contract_acceptance.stripe_checkout_session_id', $session->id);
            $tenant->update(['data' => $tenantData]);
            $tenant->refresh();

            return ApiResponseService::success([
                'checkout_url' => $session->url,
                'tenant_id' => $tenant->id,
                'session_id' => $session->id,
                'tenant_slug' => $tenant->slug,
            ], 'CHECKOUT_TENANT_CREATED_SUCCESSFULLY');
        } catch (ValidationException $e) {
            return ApiResponseService::validationError($e->errors());
        } catch (TenantDatabaseAlreadyExistsException $e) {
            $this->audit('tenant.signup_failed', 'Signup falhou: banco de dados do tenant já existe.', [
                'error' => $e->getMessage(),
                'slug' => $validated['slug'] ?? null,
                'admin_email' => $validated['admin_email'] ?? null,
                'reason' => 'database_already_exists',
            ]);

            return ApiResponseService::validationError([
                'slug' => [language()->t('SUBDOMAIN_UNVAVAILABLE')]
            ]);
        } catch (\Exception $e) {
            report($e);

            $this->audit('tenant.signup_failed', 'Signup falhou: ' . Str::limit($e->getMessage(), 200), [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'slug' => $validated['slug'] ?? null,
                'plan_slug' => $validated['plan_slug'] ?? null,
                'admin_email' => $validated['admin_email'] ?? null,
                'reason' => 'exception',
            ]);

            return ApiResponseService::error(
                'SIGNUP_ERROR',
                'SIGNUP_ERROR',
                app()->environment('local') ? $e->getMessage() : null,
                500
            );
        }
    }

    /**
     * Get signup status by session ID.
     *
     * GET /api/v1/signup/{sessionId}/status
     */
    public function status(string $sessionId)
    {
        try {
            $session = $this->billingService->retrieveCheckoutSession($sessionId);

            $tenantId = data_get($session, 'metadata.tenant_id');
            $tenant = $tenantId ? Tenant::find($tenantId) : null;
            $tenant ??= $this->billingService->findTenantBySignupCheckoutSessionId($sessionId);

            if (!$tenant) {
                return ApiResponseService::notFound('SESSION_NOT_FOUND');
            }

            $storedSessionId = $this->billingService->getSignupCheckoutSessionId($tenant);
            if ($storedSessionId && $storedSessionId !== ($session->id ?? null)) {
                return ApiResponseService::notFound('SESSION_NOT_FOUND');
            }

            return ApiResponseService::success([
                'status' => $tenant->status,
                'payment_status' => $session->payment_status,
                'is_ready' => $tenant->isActive() && $tenant->database_created,
                'tenant_slug' => $tenant->slug,
            ]);
        } catch (\Exception $e) {
            return ApiResponseService::notFound('SESSION_NOT_FOUND');
        }
    }

    /**
     * Create a price on-the-fly if stripe_price_id is not set.
     */
    protected function createPriceOnTheFly(Plan $plan): string
    {
        $this->audit('tenant.signup_price_created_on_the_fly', 'Plano sem stripe_price_id. Criando price emergencialmente.', [
            'plan_id' => $plan->id,
            'plan_slug' => $plan->slug,
            'price_in_cents' => $plan->price,
        ]);

        // Create a product first
        $product = Cashier::stripe()->products->create([
            'name' => $plan->name,
            'description' => $plan->description,
        ]);

        // Create a price for the product
        $price = Cashier::stripe()->prices->create([
            'product' => $product->id,
            'unit_amount' => $plan->price,
            'currency' => config('cashier.currency', 'brl'),
            'recurring' => [
                'interval' => 'month',
            ],
        ]);

        // Update the plan with the new price ID
        $plan->update(['stripe_price_id' => $price->id]);

        return $price->id;
    }

    protected function signupSuccessUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/') . '/signup/success?session_id={CHECKOUT_SESSION_ID}';
    }

    protected function signupCancelUrl(string $planSlug): string
    {
        $query = http_build_query([
            'plan' => $planSlug,
            'cancelled' => 1,
        ]);

        return rtrim((string) config('app.frontend_url'), '/') . '/cadastro?' . $query . '&session_id={CHECKOUT_SESSION_ID}';
    }

    protected function getSignupUsageContractConfig(): array
    {
        $configured = (array) config('legal.signup_usage_contract', []);

        return array_merge([
            'key' => 'signup_usage_contract',
            'title' => 'Contrato de Utilização da Plataforma SIG.APP',
            'version' => 'v2026-02-25',
            'effective_at' => '2026-02-25T00:00:00-03:00',
            'url' => '/juridico/contrato-utilizacao',
            'hash' => null,
        ], $configured);
    }

    protected function makeSignupContractAcceptancePayload(
        array $validated,
        array $contractConfig,
        Request $request,
        string $requestedSlug,
        string $effectiveSlug
    ): array {
        return [
            'document_key' => $contractConfig['key'] ?? 'signup_usage_contract',
            'document_title' => $contractConfig['title'] ?? 'Contrato de Utilização da Plataforma SIG.APP',
            'document_version' => $contractConfig['version'] ?? null,
            'document_hash' => $contractConfig['hash'] ?? null,
            'document_url' => $contractConfig['url'] ?? null,
            'accepted' => true,
            'accepted_at' => now()->toIso8601String(),
            'accepted_by_name' => $validated['admin_name'] ?? null,
            'accepted_by_email' => $validated['admin_email'] ?? null,
            'accepted_in_capacity' => 'organization_admin_signup',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'organization_name' => $validated['organization_name'] ?? null,
            'plan_slug' => $validated['plan_slug'] ?? null,
            'tenant_slug_requested' => $requestedSlug,
            'tenant_slug_effective' => $effectiveSlug,
            'stripe_checkout_session_id' => null,
        ];
    }
}
