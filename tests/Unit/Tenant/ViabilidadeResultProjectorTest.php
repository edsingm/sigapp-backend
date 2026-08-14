<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Exceptions\PlanFeatureDisabledException;
use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantEntitlement;
use App\Services\Tenant\Viabilidade\ViabilidadeResultProjector;
use Database\Seeders\EntitlementSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViabilidadeResultProjectorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->seed(EntitlementSeeder::class);

        $plan = Plan::query()->where('slug', 'basico')->firstOrFail();
        $this->tenant = Tenant::query()->create([
            'name' => 'Projetor',
            'slug' => 'projetor',
            'status' => Tenant::STATUS_ACTIVE,
            'plan_id' => $plan->id,
            'admin_name' => 'Admin',
            'admin_email' => 'admin@projector.test',
            'admin_password' => 'password',
        ]);
        tenancy()->tenant = $this->tenant;
        tenancy()->initialized = true;
    }

    protected function tearDown(): void
    {
        tenancy()->tenant = null;
        tenancy()->initialized = false;
        parent::tearDown();
    }

    public function test_it_omits_disabled_sections_from_persisted_result(): void
    {
        $projected = app(ViabilidadeResultProjector::class)->project([
            'vgv' => 100,
            'dre_itens' => ['receita' => 90],
            'indicadores' => ['tir' => 10],
            'fluxo_mensal' => [['mes' => '2026-01']],
            'dados_produtos' => [['id' => 1]],
        ]);

        self::assertArrayHasKey('vgv', $projected);
        self::assertArrayHasKey('dre_itens', $projected);
        self::assertArrayHasKey('indicadores', $projected);
        self::assertArrayNotHasKey('fluxo_mensal', $projected);
        self::assertArrayNotHasKey('dados_produtos', $projected);
    }

    public function test_explicit_disabled_include_is_rejected_and_override_enables_it(): void
    {
        $projector = app(ViabilidadeResultProjector::class);

        try {
            $projector->assertExplicitIncludesAllowed('fluxo_mensal');
            self::fail('O include desabilitado deveria falhar.');
        } catch (PlanFeatureDisabledException $exception) {
            self::assertSame('viabilities.cash_flow', $exception->feature);
        }

        $entitlement = Entitlement::query()->where('key', 'viabilities.cash_flow')->firstOrFail();
        TenantEntitlement::query()->create([
            'tenant_id' => $this->tenant->id,
            'entitlement_id' => $entitlement->id,
            'value' => true,
            'price' => 0,
        ]);

        $projector->assertExplicitIncludesAllowed('fluxo_mensal');
        self::assertTrue($projector->allows('cash_flow'));
    }
}
