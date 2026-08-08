<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Models\Central\AiCreditTransaction;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonPurchase;
use App\Repositories\Contracts\AiCreditTransactionRepositoryInterface;
use App\Services\Billing\AiCreditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AiCreditServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::setDefaultDriver('array');
        Carbon::setTestNow('2026-08-08 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_paid_purchase_grants_accumulative_credit_once_per_purchase_reference(): void
    {
        $repository = $this->repository();
        $purchase = (new TenantAddonPurchase)->forceFill([
            'id' => 42,
            'tenant_id' => 'tenant-credit',
            'billing_addon_id' => 2,
            'quantity' => 3,
            'grant_snapshot' => [
                'grants' => [
                    ['key' => 'ai_budget', 'type' => 'limit', 'unit_value' => 5.0],
                ],
            ],
        ]);

        $repository->shouldReceive('creditPurchase')
            ->once()
            ->with($purchase, 15.0, 'addon-purchase:42')
            ->andReturn(new AiCreditTransaction);

        $this->assertSame(15.0, (new AiCreditService($repository))->grantFromPurchase($purchase));
    }

    public function test_monthly_consumption_can_use_balance_restored_from_the_current_month_entry(): void
    {
        $repository = $this->repository();
        $tenant = (new Tenant)->forceFill(['id' => 'tenant-credit']);

        $repository->shouldReceive('monthConsumption')
            ->once()
            ->with($tenant, '2026-08')
            ->andReturn(2.0);
        $repository->shouldReceive('balance')->once()->with($tenant)->andReturn(3.5);
        $repository->shouldReceive('syncMonthConsumption')
            ->once()
            ->with($tenant, '2026-08', 5.0)
            ->andReturn(new AiCreditTransaction);

        $this->assertSame(
            5.0,
            (new AiCreditService($repository))->syncMonthlyConsumption($tenant, 5.0),
        );
    }

    public function test_monthly_consumption_rejects_an_amount_above_persistent_balance(): void
    {
        $repository = $this->repository();
        $tenant = (new Tenant)->forceFill(['id' => 'tenant-credit']);

        $repository->shouldReceive('monthConsumption')->once()->andReturn(1.0);
        $repository->shouldReceive('balance')->once()->andReturn(2.0);
        $repository->shouldReceive('syncMonthConsumption')->never();

        $this->expectException(InvalidArgumentException::class);
        (new AiCreditService($repository))->syncMonthlyConsumption($tenant, 3.01);
    }

    private function repository(): AiCreditTransactionRepositoryInterface&MockInterface
    {
        $repository = Mockery::mock(AiCreditTransactionRepositoryInterface::class);
        if (! $repository instanceof AiCreditTransactionRepositoryInterface) {
            throw new \RuntimeException('Mock de ledger inválido.');
        }

        return $repository;
    }
}
