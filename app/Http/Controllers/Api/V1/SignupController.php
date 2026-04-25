<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SignupRequest;
use App\Models\Central\Tenant;
use App\Models\Central\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\ApiResponseService;
use App\Services\Billing\StripeCheckoutService;
use App\Services\Billing\TenantBillingService;
use App\Services\Signup\TenantSignupService;
use App\Traits\LogsAudit;
use App\Exceptions\SignupSlugReservedException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Exceptions\TenantDatabaseAlreadyExistsException;

class SignupController extends Controller
{
    use LogsAudit;

    public function __construct(
        protected TenantBillingService $billingService,
        protected TenantSignupService $signupService,
        protected StripeCheckoutService $checkoutService,
        protected PlanRepositoryInterface $planRepository,
        protected TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Cria um novo tenant (status pendente) e retorna uma URL de Checkout do Stripe.
     *
     * POST /api/v1/signup
     */
    public function store(SignupRequest $request)
    {
        $validated = $request->validated();

        $plan = $this->planRepository->findActiveBySlug($validated['plan_slug']);

        if (! $plan) {
            return ApiResponseService::notFound('PLAN_NOT_FOUND');
        }

        try {
            ['tenant' => $tenant, 'contract_acceptance' => $contractAcceptance] =
                $this->signupService->createPendingTenant($validated, $plan, $request);

            $this->auditContractAcceptance($tenant, $plan, $validated, $contractAcceptance);
            $this->auditSignupStarted($tenant, $plan, $validated);

            $customer = $this->checkoutService->createCustomer($tenant, $validated);

            $session = $this->checkoutService->createSubscriptionSession($tenant, $plan, $customer->id);

            $this->signupService->storeCheckoutSessionId($tenant, $session->id);

            return ApiResponseService::success([
                'checkout_url' => $session->url,
                'tenant_id' => $tenant->id,
                'session_id' => $session->id,
                'tenant_slug' => $tenant->slug,
            ], 'CHECKOUT_TENANT_CREATED_SUCCESSFULLY');

        } catch (ValidationException $e) {
            return ApiResponseService::validationError($e->errors());
        } catch (SignupSlugReservedException $e) {
            return ApiResponseService::conflict('SUBDOMAIN_RESERVED');
        } catch (TenantDatabaseAlreadyExistsException $e) {
            $this->audit('tenant.signup_failed', 'Signup falhou: banco de dados do tenant já existe.', [
                'error' => $e->getMessage(),
                'slug' => $validated['slug'] ?? null,
                'admin_email' => $validated['admin_email'] ?? null,
                'reason' => 'database_already_exists',
            ]);

            return ApiResponseService::validationError([
                'slug' => [language()->t('SUBDOMAIN_UNVAVAILABLE')],
            ]);
        } catch (\Exception $e) {
            report($e);

            $this->audit('tenant.signup_failed', 'Signup falhou: '.Str::limit($e->getMessage(), 200), [
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
     * Busca o status do cadastro pelo ID da sessão do Stripe.
     *
     * GET /api/v1/signup/{sessionId}/status
     */
    public function status(string $sessionId)
    {
        try {
            $session = $this->billingService->retrieveCheckoutSession($sessionId);

            $tenantId = data_get($session, 'metadata.tenant_id');
            $tenant = $tenantId ? $this->tenantRepository->findById($tenantId) : null;
            $tenant ??= $this->billingService->findTenantBySignupCheckoutSessionId($sessionId);

            if (! $tenant) {
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
        } catch (\Exception) {
            return ApiResponseService::notFound('SESSION_NOT_FOUND');
        }
    }

    private function auditContractAcceptance(Tenant $tenant, Plan $plan, array $validated, array $contractAcceptance): void
    {
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
    }

    private function auditSignupStarted(Tenant $tenant, Plan $plan, array $validated): void
    {
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
    }
}
