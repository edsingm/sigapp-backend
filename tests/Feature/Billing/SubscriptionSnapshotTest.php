<?php

namespace Tests\Feature\Billing;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Billing\TenantBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cobre a exposição de `scheduled_plan` (downgrade agendado) no snapshot da
 * assinatura. Usa tenant sem stripe_id para pular o bloco que chama a API do
 * Stripe — o campo `scheduled_plan` é montado independentemente disso.
 */
class SubscriptionSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(string $name, int $sortOrder): Plan
    {
        return Plan::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'stripe_price_id' => 'price_'.uniqid(),
            'price' => 10000,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'trial_days' => 0,
        ]);
    }

    private function insertTenant(int $planId, ?int $scheduledPlanId): Tenant
    {
        $id = Str::uuid()->toString();

        DB::table('tenants')->insert([
            'id' => $id,
            'name' => 'Test Tenant',
            'slug' => 'test-'.uniqid(),
            'status' => Tenant::STATUS_ACTIVE,
            'stripe_id' => null,
            'plan_id' => $planId,
            'scheduled_plan_id' => $scheduledPlanId,
            'admin_email' => 'admin@test.com',
            'admin_name' => 'Admin',
            'database_created' => false,
            'trial_extended' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->findOrFail($id);

        return $tenant;
    }

    public function test_snapshot_exposes_scheduled_plan_when_downgrade_is_pending(): void
    {
        $current = $this->makePlan('Profissional', 2);
        $scheduled = $this->makePlan('Starter', 1);

        $tenant = $this->insertTenant($current->getKey(), $scheduled->getKey());

        $snapshot = (new TenantBillingService)->getSubscriptionSnapshot($tenant);

        $this->assertIsArray($snapshot['scheduled_plan']);
        $this->assertSame('starter', $snapshot['scheduled_plan']['slug']);
        $this->assertSame('Starter', $snapshot['scheduled_plan']['name']);
    }

    public function test_snapshot_scheduled_plan_is_null_without_pending_downgrade(): void
    {
        $current = $this->makePlan('Profissional', 2);

        $tenant = $this->insertTenant($current->getKey(), null);

        $snapshot = (new TenantBillingService)->getSubscriptionSnapshot($tenant);

        $this->assertNull($snapshot['scheduled_plan']);
    }
}
