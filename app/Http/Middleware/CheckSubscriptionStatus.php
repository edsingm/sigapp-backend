<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!tenancy()->initialized) {
            return $next($request);
        }

        $tenant = tenancy()->tenant;

        // Check if tenant is active
        if (!$tenant->isActive()) {
            return ApiResponseService::error(
                'SUBSCRIPTION_INACTIVE',
                match ($tenant->status) {
                    'pending' => 'Assinatura pendente de pagamento',
                    'suspended' => 'Assinatura suspensa por falta de pagamento',
                    'cancelled' => 'Assinatura cancelada',
                    default => 'Assinatura inativa',
                },
                [
                    'status' => $tenant->status,
                    'support_url' => 'https://sigapp.com.br/suporte',
                ],
                403
            );
        }

        // Check if trial has ended without active subscription
        if ($tenant->trialEnded() && !$tenant->stripe_subscription_id) {
            return ApiResponseService::error(
                'TRIAL_ENDED',
                'Período de teste encerrado. Por favor, assine um plano para continuar.',
                [
                    'trial_ended_at' => $tenant->trial_ends_at->toIso8601String(),
                    'checkout_url' => '/api/v1/checkout',
                ],
                403
            );
        }

        return $next($request);
    }
}
