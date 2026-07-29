<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Central\Tenant;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Services\Ai\Tools\GetDashboardSummaryTool;
use App\Services\Ai\Tools\GetTerrenoDetailsTool;
use App\Services\PlanMatrixService;
use Database\Factories\Tenant\TerrenoFactory;
use Database\Factories\Tenant\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Tools\Request;
use Mockery;
use Stancl\Tenancy\Tenancy;
use Tests\TestCase;

class AiToolsAuthorizationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Terreno $terreno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
        $this->user = UserFactory::new()->createOne();
        $this->terreno = TerrenoFactory::new()->createOne(['created_by' => $this->user->id]);

        app(Tenancy::class)->tenant = new Tenant;
        $this->app->instance(PlanMatrixService::class, Mockery::mock(PlanMatrixService::class, function ($mock): void {
            $mock->shouldReceive('hasFeatureForTenant')->andReturnTrue();
        }));

        Gate::before(static function (User $user, string $ability, array $arguments): bool {
            $subject = $arguments[0] ?? null;

            return $subject === Terreno::class || $subject instanceof Terreno;
        });
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        app(Tenancy::class)->tenant = null;
        Mockery::close();

        parent::tearDown();
    }

    public function test_terrain_details_omit_related_modules_without_their_permissions(): void
    {
        $payload = json_decode((string) app(GetTerrenoDetailsTool::class)->handle(new Request([
            'terreno_id' => $this->terreno->id,
            'mode' => 'full',
            'include_viabilidades' => true,
        ])), true);

        $this->assertTrue($payload['ok'] ?? false);
        $this->assertSame(
            ['viabilidades', 'negociacao', 'contrato', 'projetos'],
            $payload['data']['restricted_sections'] ?? []
        );
        $this->assertArrayNotHasKey('viabilidade_atual', $payload['data']);
        $this->assertArrayNotHasKey('negociacao_atual', $payload['data']);
        $this->assertArrayNotHasKey('contrato_atual', $payload['data']);
        $this->assertArrayNotHasKey('projetos', $payload['data']);
        $this->assertArrayNotHasKey('documentos', $payload['data']['totais']);
        $this->assertArrayNotHasKey('proprietarios', $payload['data']['totais']);
        $this->assertArrayNotHasKey('viabilidades', $payload['data']['totais']);
        $this->assertArrayNotHasKey('projetos', $payload['data']['totais']);
    }

    public function test_dashboard_omits_aggregates_from_unauthorized_modules(): void
    {
        $payload = json_decode((string) app(GetDashboardSummaryTool::class)->handle(new Request), true);

        $this->assertTrue($payload['ok'] ?? false);
        $this->assertArrayHasKey('terrenos', $payload['data']);
        $this->assertSame(
            ['viabilidades', 'comite', 'negociacao'],
            $payload['data']['restricted_sections'] ?? []
        );
        $this->assertArrayNotHasKey('viabilidades', $payload['data']);
        $this->assertArrayNotHasKey('vgv_estimado', $payload['data']);
        $this->assertArrayNotHasKey('comite', $payload['data']);
        $this->assertArrayNotHasKey('negociacoes_ativas', $payload['data']);
    }
}
