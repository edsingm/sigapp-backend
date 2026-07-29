<?php

namespace Tests\Unit;

use App\Models\Central\Tenant;
use App\Models\Tenant\Terreno;
use App\Services\Ai\Tools\AiToolAuth;
use App\Services\Ai\Tools\AiToolResponse;
use App\Services\PlanMatrixService;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Stancl\Tenancy\Tenancy;
use Tests\TestCase;

class AiToolAuthTest extends TestCase
{
    protected function tearDown(): void
    {
        app(Tenancy::class)->tenant = null;
        Mockery::close();
        parent::tearDown();
    }

    public function test_ensure_view_any_returns_denied_envelope(): void
    {
        Gate::shouldReceive('allows')->once()->with('viewAny', Terreno::class)->andReturn(false);

        $auth = app(AiToolAuth::class);
        $result = $auth->ensureViewAny(Terreno::class, 'Acesso negado: você não tem permissão para listar terrenos.');
        $payload = json_decode((string) $result, true);

        $this->assertSame(AiToolResponse::DENIED, $payload['code']);
        $this->assertStringContainsString('não tem permissão', $payload['message']);
    }

    public function test_ensure_feature_returns_plan_denied_without_tenant(): void
    {
        app(Tenancy::class)->tenant = null;

        $auth = app(AiToolAuth::class);
        $result = $auth->ensureFeature('committee', 'Acesso negado: seu plano não inclui comitê de revisão.');
        $payload = json_decode((string) $result, true);

        $this->assertSame(AiToolResponse::PLAN_DENIED, $payload['code']);
        $this->assertStringContainsString('plano não inclui', $payload['message']);
    }

    public function test_ensure_feature_ok_when_tenant_has_feature(): void
    {
        $tenant = new Tenant;
        app(Tenancy::class)->tenant = $tenant;

        $plan = Mockery::mock(PlanMatrixService::class);
        $plan->shouldReceive('hasFeatureForTenant')->once()->with($tenant, 'committee')->andReturn(true);
        $this->app->instance(PlanMatrixService::class, $plan);

        $auth = app(AiToolAuth::class);

        $this->assertNull(
            $auth->ensureFeature('committee', 'Acesso negado: seu plano não inclui comitê de revisão.')
        );
    }

    public function test_ensure_authenticated_denies_guest(): void
    {
        $auth = app(AiToolAuth::class);
        $payload = json_decode((string) $auth->ensureAuthenticated(), true);

        $this->assertSame(AiToolResponse::DENIED, $payload['code']);
    }

    public function test_ensure_terreno_view_validates_id(): void
    {
        $auth = app(AiToolAuth::class);
        $result = $auth->ensureTerrenoView(0);
        $payload = json_decode((string) $result, true);

        $this->assertSame(AiToolResponse::VALIDATION, $payload['code']);
    }

    public function test_ensure_rate_limit_blocks_after_max_hits(): void
    {
        $auth = app(AiToolAuth::class);

        $this->assertNull($auth->ensureRateLimit('ai-test-bucket', 1, 60, 'Limite atingido.'));

        $payload = json_decode(
            (string) $auth->ensureRateLimit('ai-test-bucket', 1, 60, 'Limite atingido.'),
            true
        );

        $this->assertSame(AiToolResponse::ERROR, $payload['code']);
        $this->assertStringContainsString('Limite atingido', $payload['message']);
    }
}
