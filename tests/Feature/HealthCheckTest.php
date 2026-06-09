<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCentralAdmin(): void
    {
        $user = User::factory()->admin()->create();

        Sanctum::actingAs($user, ['admin']);
    }

    public function test_check_retorna_status_ok_quando_todos_checks_passam(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response(['object' => 'balance'], 200),
            'openrouter.ai/*' => Http::response(['data' => []], 200),
        ]);

        Config::set('cashier.secret', 'sk_test_123');
        Config::set('ai.providers.openrouter.key', 'sk-or-123');

        $report = app(HealthCheckService::class)->check();

        $this->assertSame('ok', $report['status']);
        $this->assertArrayHasKey('timestamp', $report);
        $this->assertArrayHasKey('checks', $report);
        $this->assertSame('ok', $report['checks']['database']['status']);
        $this->assertSame('ok', $report['checks']['cache']['status']);
        $this->assertSame('ok', $report['checks']['storage']['status']);
        $this->assertSame('ok', $report['checks']['queue']['status']);
        $this->assertSame('ok', $report['checks']['stripe']['status']);
        $this->assertSame('ok', $report['checks']['openrouter']['status']);
    }

    public function test_check_status_geral_down_quando_database_central_falha(): void
    {
        DB::shouldReceive('connection')->andReturnSelf();
        DB::shouldReceive('select')->andThrow(new \RuntimeException('Conexão recusada'));

        $report = app(HealthCheckService::class)->check();

        $this->assertSame('down', $report['status']);
        $this->assertSame('down', $report['checks']['database']['status']);
    }

    public function test_check_status_degraded_quando_servico_externo_falha(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response(['error' => 'unauthorized'], 500),
        ]);
        Config::set('cashier.secret', 'sk_test_123');
        Config::set('ai.providers.openrouter.key', null);

        $report = app(HealthCheckService::class)->check();

        $this->assertSame('degraded', $report['status']);
        $this->assertSame('down', $report['checks']['stripe']['status']);
        $this->assertSame('ok', $report['checks']['database']['status']);
    }

    public function test_check_storage_down_produz_status_geral_down(): void
    {
        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('put')->andThrow(new \RuntimeException('Disk indisponível'));

        $report = app(HealthCheckService::class)->check();

        $this->assertSame('down', $report['status']);
        $this->assertSame('down', $report['checks']['storage']['status']);
    }

    public function test_check_sem_chave_stripe_retorna_ok_com_mensagem_nao_configurado(): void
    {
        Config::set('cashier.secret', null);

        $report = app(HealthCheckService::class)->check();

        $this->assertSame('ok', $report['checks']['stripe']['status']);
        $this->assertSame('Não configurado', $report['checks']['stripe']['message']);
    }

    public function test_check_sem_chave_openrouter_retorna_ok_com_mensagem_nao_configurado(): void
    {
        Config::set('ai.providers.openrouter.key', null);

        $report = app(HealthCheckService::class)->check();

        $this->assertSame('ok', $report['checks']['openrouter']['status']);
        $this->assertSame('Não configurado', $report['checks']['openrouter']['message']);
    }

    public function test_check_rota_central_publica_retorna_apenas_status_ok_quando_saudavel(): void
    {
        Http::fake();

        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertExactJson([
                'status' => 'ok',
            ]);
    }

    public function test_check_rota_central_publica_retorna_503_quando_check_critico_falha(): void
    {
        DB::shouldReceive('connection')->andReturnSelf();
        DB::shouldReceive('select')->andThrow(new \RuntimeException('Conexão recusada'));

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(503)
            ->assertExactJson([
                'status' => 'down',
            ]);
    }

    public function test_check_rota_central_detalhada_exige_autenticacao_de_admin_central(): void
    {
        $response = $this->withHeader('Host', 'localhost')->getJson('/api/v1/health/details');

        $response->assertUnauthorized();
    }

    public function test_check_rota_central_detalhada_retorna_relatorio_completo_para_admin_central(): void
    {
        Http::fake();
        $this->actingAsCentralAdmin();

        $response = $this->withHeader('Host', 'localhost')->getJson('/api/v1/health/details');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'database',
                    'cache',
                    'storage',
                    'queue',
                    'stripe',
                    'openrouter',
                ],
            ]);
    }
}
