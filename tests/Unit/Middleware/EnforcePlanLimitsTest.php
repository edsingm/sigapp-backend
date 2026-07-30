<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnforcePlanLimits;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\PlanMatrixService;
use App\Services\UsageMetricsService;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnforcePlanLimitsTest extends TestCase
{
    protected function tearDown(): void
    {
        tenancy()->tenant = null;
        tenancy()->initialized = false;

        parent::tearDown();
    }

    public function test_last_slot_is_consumed_before_the_next_creation_checks_the_limit(): void
    {
        $plan = new Plan;
        $plan->forceFill(['id' => 1, 'name' => 'Limitado']);
        $tenant = new Tenant;
        $tenant->forceFill(['id' => 'limit-tenant']);
        $tenant->setRelation('plan', $plan);
        tenancy()->tenant = $tenant;
        tenancy()->initialized = true;

        $count = 0;
        $usage = $this->createMock(UsageMetricsService::class);
        $usage->expects(self::exactly(2))
            ->method('getUserCount')
            ->willReturnCallback(function () use (&$count): int {
                return $count;
            });
        $matrix = $this->createMock(PlanMatrixService::class);
        $matrix->expects(self::exactly(2))
            ->method('isUnlimitedLimitForTenant')
            ->with($tenant, 'users')
            ->willReturn(false);
        $matrix->expects(self::exactly(2))
            ->method('getLimitForTenant')
            ->with($tenant, 'users')
            ->willReturn(1);
        $middleware = new EnforcePlanLimits($usage, $matrix);
        $request = Request::create('/api/v1/users', 'POST');
        $next = static function () use (&$count) {
            $count++;

            return response()->json(['created' => true], 201);
        };

        self::assertSame(201, $middleware->handle($request, $next, 'users')->getStatusCode());
        self::assertSame(403, $middleware->handle($request, $next, 'users')->getStatusCode());
        self::assertSame(1, $count);
    }
}
