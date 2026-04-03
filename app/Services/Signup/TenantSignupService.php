<?php

namespace App\Services\Signup;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Traits\LogsAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Database\Models\Domain;

class TenantSignupService
{
    use LogsAudit;

    /**
     * Cria um tenant pendente com validação de slug, domínio e aceite de contrato.
     *
     * @param  array<string, mixed>  $validated
     * @return array{tenant: Tenant, contract_acceptance: array<string, mixed>}
     *
     * @throws ValidationException
     */
    public function createPendingTenant(array $validated, Plan $plan, Request $request): array
    {
        $requestedSlug = (string) ($validated['slug'] ?? '');
        $effectiveSlug = Str::slug($requestedSlug);
        $contractConfig = $this->getSignupUsageContractConfig();

        $tenant = DB::transaction(function () use ($validated, $plan, $request, $contractConfig, $requestedSlug, $effectiveSlug) {
            $existingTenant = Tenant::where('slug', $effectiveSlug)->lockForUpdate()->first();
            $existingDomain = Domain::query()->where('domain', $effectiveSlug)->lockForUpdate()->first();

            if ($existingTenant || $existingDomain) {
                $this->audit('tenant.signup_slug_conflict', "Slug '{$effectiveSlug}' indisponível no cadastro.", [
                    'requested_slug' => $requestedSlug,
                    'effective_slug' => $effectiveSlug,
                    'admin_email' => $validated['admin_email'],
                ]);

                throw ValidationException::withMessages([
                    'slug' => [language()->t('SUBDOMAIN_UNVAVAILABLE')],
                ]);
            }

            $contractAcceptance = $this->buildContractAcceptancePayload(
                validated: $validated,
                contractConfig: $contractConfig,
                request: $request,
                requestedSlug: $requestedSlug,
                effectiveSlug: $effectiveSlug,
            );

            $tenant = Tenant::create([
                'name' => $validated['organization_name'],
                'slug' => $effectiveSlug,
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

            $tenant->domains()->create(['domain' => $tenant->slug]);

            // Segurança: garante que o plan_id seja persistido na tabela de tenants
            if (! $tenant->getOriginal('plan_id')) {
                Tenant::whereKey($tenant->id)->update(['plan_id' => $plan->id]);
                $tenant->refresh();
            }

            return $tenant;
        });

        $contractAcceptance = data_get($tenant->data ?? [], 'signup_contract_acceptance', []);

        return ['tenant' => $tenant, 'contract_acceptance' => $contractAcceptance];
    }

    /**
     * Armazena o ID da sessão de checkout do Stripe nos dados do tenant.
     */
    public function storeCheckoutSessionId(Tenant $tenant, string $sessionId): void
    {
        $data = $tenant->data ?? [];
        data_set($data, 'signup_contract_acceptance.stripe_checkout_session_id', $sessionId);
        $tenant->update(['data' => $data]);
        $tenant->refresh();
    }

    /**
     * Busca a configuração do contrato de cadastro das configurações da aplicação com valores padrão.
     *
     * @return array<string, mixed>
     */
    public function getSignupUsageContractConfig(): array
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

    /**
     * Constrói o payload de metadados do aceite de contrato.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $contractConfig
     * @return array<string, mixed>
     */
    public function buildContractAcceptancePayload(
        array $validated,
        array $contractConfig,
        Request $request,
        string $requestedSlug,
        string $effectiveSlug,
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
