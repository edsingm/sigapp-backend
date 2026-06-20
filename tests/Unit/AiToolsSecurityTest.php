<?php

namespace Tests\Unit;

use App\Services\Ai\Tools\GetComiteTool;
use App\Services\Ai\Tools\GetLegalizacaoTool;
use App\Services\Ai\Tools\GetNegociacaoTool;
use App\Services\Ai\Tools\GetViabilidadesTool;
use App\Models\Central\Tenant;
use App\Services\PlanMatrixService;
use Laravel\Ai\Tools\Request;
use Mockery;
use Stancl\Tenancy\Tenancy;
use Tests\TestCase;

/**
 * Verifica que cada tool Pro-gated rejeita corretamente quando:
 *   1. Não há tenant inicializado (tenancy()->tenant === null)
 *   2. O plano do tenant não inclui a feature
 *   3. A feature está ativa mas o usuário não tem permissão RBAC
 *
 * Testes de execução com dados reais ficam em Feature/Tenant/.
 */
class AiToolsSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(Tenancy::class)->tenant = null;
    }

    protected function tearDown(): void
    {
        app(Tenancy::class)->tenant = null;
        Mockery::close();
        parent::tearDown();
    }

    private function mockPlanMatrix(bool $hasFeature): void
    {
        $mock = Mockery::mock(PlanMatrixService::class);
        $mock->shouldReceive('hasFeatureForTenant')->andReturn($hasFeature);
        $this->app->instance(PlanMatrixService::class, $mock);
    }

    private function setFakeTenant(): Tenant
    {
        $tenant = new Tenant;
        app(Tenancy::class)->tenant = $tenant;

        return $tenant;
    }

    // ── GetLegalizacaoTool ─────────────────────────────────────────────────────

    public function test_legalizacao_denies_when_no_tenant(): void
    {
        $result = app(GetLegalizacaoTool::class)->handle(new Request([]));

        $this->assertStringContainsString('plano não inclui', (string) $result);
    }

    public function test_legalizacao_denies_when_feature_disabled(): void
    {
        $this->setFakeTenant();
        $this->mockPlanMatrix(false);

        $result = app(GetLegalizacaoTool::class)->handle(new Request([]));

        $this->assertStringContainsString('plano não inclui', (string) $result);
    }

    public function test_legalizacao_denies_without_rbac_permission(): void
    {
        $this->setFakeTenant();
        $this->mockPlanMatrix(true);

        $result = app(GetLegalizacaoTool::class)->handle(new Request([]));

        $this->assertStringContainsString('não tem permissão', (string) $result);
    }

    // ── GetNegociacaoTool ──────────────────────────────────────────────────────

    public function test_negociacao_denies_when_no_tenant(): void
    {
        $result = app(GetNegociacaoTool::class)->handle(new Request([]));

        $this->assertStringContainsString('plano não inclui', (string) $result);
    }

    public function test_negociacao_denies_when_feature_disabled(): void
    {
        $this->setFakeTenant();
        $this->mockPlanMatrix(false);

        $result = app(GetNegociacaoTool::class)->handle(new Request([]));

        $this->assertStringContainsString('plano não inclui', (string) $result);
    }

    public function test_negociacao_denies_without_rbac_permission(): void
    {
        $this->setFakeTenant();
        $this->mockPlanMatrix(true);

        $result = app(GetNegociacaoTool::class)->handle(new Request([]));

        $this->assertStringContainsString('não tem permissão', (string) $result);
    }

    // ── GetComiteTool ──────────────────────────────────────────────────────────

    public function test_comite_denies_when_no_tenant(): void
    {
        $result = app(GetComiteTool::class)->handle(new Request([]));

        $this->assertStringContainsString('plano não inclui', (string) $result);
    }

    public function test_comite_denies_when_feature_disabled(): void
    {
        $this->setFakeTenant();
        $this->mockPlanMatrix(false);

        $result = app(GetComiteTool::class)->handle(new Request([]));

        $this->assertStringContainsString('plano não inclui', (string) $result);
    }

    public function test_comite_denies_without_rbac_permission(): void
    {
        $this->setFakeTenant();
        $this->mockPlanMatrix(true);

        $result = app(GetComiteTool::class)->handle(new Request([]));

        $this->assertStringContainsString('não tem permissão', (string) $result);
    }

    // ── GetViabilidadesTool ────────────────────────────────────────────────────

    public function test_viabilidades_denies_when_no_tenant(): void
    {
        $result = app(GetViabilidadesTool::class)->handle(new Request([]));

        $this->assertStringContainsString('plano não inclui', (string) $result);
    }

    public function test_viabilidades_denies_when_feature_disabled(): void
    {
        $this->setFakeTenant();
        $this->mockPlanMatrix(false);

        $result = app(GetViabilidadesTool::class)->handle(new Request([]));

        $this->assertStringContainsString('plano não inclui', (string) $result);
    }

    public function test_viabilidades_denies_without_rbac_permission(): void
    {
        $this->setFakeTenant();
        $this->mockPlanMatrix(true);

        $result = app(GetViabilidadesTool::class)->handle(new Request([]));

        $this->assertStringContainsString('não tem permissão', (string) $result);
    }
}
