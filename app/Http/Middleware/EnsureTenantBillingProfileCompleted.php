<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use App\Services\ApiResponseService;
use App\Services\Billing\TenantBillingProfileService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantBillingProfileCompleted
{
    public function __construct(private readonly TenantBillingProfileService $billingProfile) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenancy()->tenant;

        if (! $tenant instanceof Tenant || ! $this->billingProfile->requiresCompletion($tenant)) {
            return $next($request);
        }

        $summary = $this->billingProfile->summaryForUser($tenant, $request->user());

        return ApiResponseService::preconditionRequired(
            'TENANT_BILLING_PROFILE_INCOMPLETE',
            'TENANT_BILLING_PROFILE_INCOMPLETE',
            [
                'required_action' => $summary['required_action'],
                'can_complete' => $summary['can_complete'],
                'missing_fields' => $summary['missing_fields'],
            ],
        );
    }
}
