<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionStatus
{
    /**
     * Manipula uma requisição de entrada.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            return $next($request);
        }

        $tenant = tenancy()->tenant;

        // Verifica se o tenant está ativo
        if (! $tenant->isActive()) {
            return ApiResponseService::error(
                'SUBSCRIPTION_INACTIVE',
                match ($tenant->status) {
                    TenantStatus::PENDING->value => 'Assinatura pendente de pagamento',
                    TenantStatus::SUSPENDED->value => 'Assinatura suspensa por falta de pagamento',
                    TenantStatus::CANCELLED->value => 'Assinatura cancelada',
                    TenantStatus::SETUP_FAILED->value => 'Falha na configuração do ambiente. Entre em contato com o suporte.',
                    default => 'Assinatura inativa',
                },
                [
                    'status' => $tenant->status,
                    'support_url' => 'https://sigapp.com.br/suporte',
                    'billing_portal_available' => (bool) $tenant->stripe_id,
                ],
                403
            );
        }

        // Verifica se o período de teste terminou sem uma assinatura ativa
        if ($tenant->trialEnded() && ! $tenant->stripe_subscription_id) {
            return ApiResponseService::error(
                'TRIAL_ENDED',
                'Período de teste encerrado. Por favor, assine um plano para continuar.',
                [
                    'trial_ended_at' => $tenant->trial_ends_at->toIso8601String(),
                    'support_url' => 'https://sigapp.com.br/suporte',
                    'billing_portal_available' => (bool) $tenant->stripe_id,
                ],
                403
            );
        }

        return $next($request);
    }
}
