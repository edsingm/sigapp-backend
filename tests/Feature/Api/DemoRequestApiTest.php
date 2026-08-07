<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Events\DemoRequestReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DemoRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([DemoRequestReceived::class]);
    }

    public function test_public_endpoint_registers_demo_request_and_dispatches_event(): void
    {
        $response = $this
            ->withHeader('Host', 'localhost')
            ->withHeader('User-Agent', 'Demo browser')
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->postJson('/api/v1/demo-request', [
                'name' => 'Edson',
                'email' => 'EDSON@EXAMPLE.COM',
                'company' => 'Empresa Teste',
                'city' => 'Marília',
                'role' => 'Direção / sócio',
                'land_context' => 'Terreno para análise',
                'source' => 'demonstracao',
                'page' => 'demonstracao',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Solicitação de demonstração recebida. Entraremos em contato em breve.');

        $this->assertDatabaseHas('demo_requests', [
            'name' => 'Edson',
            'email' => 'edson@example.com',
            'company' => 'Empresa Teste',
            'city' => 'Marília',
            'source' => 'demonstracao',
            'page' => 'demonstracao',
            'ip_hash' => hash('sha256', '198.51.100.10'),
        ]);

        Event::assertDispatched(DemoRequestReceived::class);
    }

    public function test_public_endpoint_rejects_invalid_required_fields(): void
    {
        $response = $this
            ->withHeader('Host', 'localhost')
            ->postJson('/api/v1/demo-request', [
                'name' => 'A',
                'email' => 'invalid-email',
                'company' => '',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['name', 'email', 'company']);

        $this->assertDatabaseCount('demo_requests', 0);
    }

    public function test_public_endpoint_has_a_dedicated_rate_limit(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this
                ->withHeader('Host', 'localhost')
                ->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
                ->postJson('/api/v1/demo-request', [
                    'name' => 'Visitante',
                    'email' => "visitor-{$attempt}@example.com",
                    'company' => 'Empresa Teste',
                ])
                ->assertCreated();
        }

        $this
            ->withHeader('Host', 'localhost')
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->postJson('/api/v1/demo-request', [
                'name' => 'Visitante',
                'email' => 'visitor-six@example.com',
                'company' => 'Empresa Teste',
            ])
            ->assertTooManyRequests();
    }
}
