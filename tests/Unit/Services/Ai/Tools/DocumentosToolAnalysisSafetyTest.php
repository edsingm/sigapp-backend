<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Tools;

use App\Models\Central\Tenant;
use App\Models\Tenant\Documento;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Services\Ai\Tools\DocumentosTool;
use App\Services\PlanMatrixService;
use App\Services\Tenant\DocumentIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Ai\Tools\Request;
use Mockery;
use Stancl\Tenancy\Tenancy;
use Tests\TestCase;

class DocumentosToolAnalysisSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
    }

    protected function tearDown(): void
    {
        app(Tenancy::class)->tenant = null;
        Mockery::close();
        parent::tearDown();
    }

    public function test_tool_returns_envelope_when_request_analysis_throws(): void
    {
        $user = User::query()->create([
            'name' => 'Tool User',
            'email' => 'tool-doc@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->actingAs($user);

        $terreno = Terreno::query()->create([
            'nome' => 'Terreno Tool',
            'created_by' => $user->id,
        ]);
        $documento = Documento::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => 'MATRICULA 48969.pdf',
            'tipo' => 'matricula',
            'file_path' => 'documentos/matricula.pdf',
            'tamanho' => 100,
            'status' => 'pendente',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Gate::shouldReceive('allows')->andReturn(true);
        Gate::shouldReceive('denies')->andReturn(false);

        $tenant = new Tenant;
        app(Tenancy::class)->tenant = $tenant;

        $plan = Mockery::mock(PlanMatrixService::class);
        $plan->shouldReceive('hasFeatureForTenant')->andReturn(true);
        $this->app->instance(PlanMatrixService::class, $plan);

        $intelligence = Mockery::mock(DocumentIntelligenceService::class);
        $intelligence->shouldReceive('requestAnalysis')
            ->once()
            ->andThrow(new \RuntimeException('Não foi possível gerar o embedding do documento.'));
        $this->app->instance(DocumentIntelligenceService::class, $intelligence);

        $result = json_decode((string) (new DocumentosTool)->handle(new Request([
            'document_id' => $documento->id,
        ])), true);

        $this->assertTrue($result['ok'] ?? false);
        $this->assertSame('failed', $result['data']['analysis']['status'] ?? null);
        $this->assertStringContainsString('Não foi possível', (string) ($result['data']['analysis']['message'] ?? ''));
    }
}
