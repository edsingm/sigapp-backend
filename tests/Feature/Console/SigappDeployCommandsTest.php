<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Central\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class SigappDeployCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_aplica_migrations_e_nao_executa_seed(): void
    {
        $this->assertSame(0, Plan::query()->count());

        $this->pendingArtisan('sigapp:release', ['--no-cache' => true])
            ->expectsOutputToContain('Caches de config/rotas/views omitidos.')
            ->assertSuccessful();

        $this->assertSame(0, Plan::query()->count());
    }

    public function test_bootstrap_recusa_banco_ja_inicializado(): void
    {
        $this->makePlan();

        $this->pendingArtisan('sigapp:bootstrap', ['--no-cache' => true])
            ->expectsOutputToContain('O banco já está inicializado.')
            ->assertFailed();
    }

    public function test_bootstrap_cancela_quando_force_e_recusado_no_tty(): void
    {
        $this->makePlan();

        $this->pendingArtisan('sigapp:bootstrap', ['--force' => true, '--no-cache' => true])
            ->expectsConfirmation(
                'O banco já está inicializado. Reexecutar o seed pode alterar dados existentes. Continuar?',
                'no',
            )
            ->expectsOutputToContain('Bootstrap cancelado.')
            ->assertSuccessful();

        $this->assertSame(1, Plan::query()->count());
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function pendingArtisan(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);
        $this->assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }

    private function makePlan(): Plan
    {
        return Plan::query()->create([
            'name' => 'Test',
            'slug' => 'test-plan',
            'price' => 1000,
            'sort_order' => 1,
            'is_active' => true,
            'trial_days' => 0,
        ]);
    }
}
