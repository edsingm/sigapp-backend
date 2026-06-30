<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tenant;

use App\Enums\PerfilFinanciamento;
use App\Models\Tenant\PremissasViabilidade;
use App\Repositories\Contracts\PremissasViabilidadeRepositoryInterface;
use App\Services\Tenant\PremissasViabilidadeCrudService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PremissasViabilidadeCrudServiceTest extends TestCase
{
    public function test_create_define_versao_automaticamente(): void
    {
        Carbon::setTestNow('2026-06-29 10:00:00');

        $novaPremissa = new PremissasViabilidade;
        $novaPremissa->forceFill([
            'id' => 12,
            'versao' => 5,
            'vigente_em' => '2026-06-29',
        ]);

        $repository = $this->createMock(PremissasViabilidadeRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('nextVersion')
            ->with(PerfilFinanciamento::CEF->value)
            ->willReturn(5);
        $repository->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $payload): bool {
                return $payload['versao'] === 5
                    && $payload['vigente_em'] === '2026-06-29'
                    && $payload['nome'] === 'Nova premissa';
            }))
            ->willReturn($novaPremissa);

        $service = new PremissasViabilidadeCrudService($repository);

        $result = $service->create([
            'nome' => 'Nova premissa',
            'perfil_financiamento' => PerfilFinanciamento::CEF->value,
            'versao' => 99,
        ]);

        $this->assertSame($novaPremissa, $result);

        Carbon::setTestNow();
    }

    public function test_update_cria_nova_versao_e_preserva_anterior(): void
    {
        Carbon::setTestNow('2026-06-29 10:00:00');
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $premissaAtual = new PremissasViabilidade;
        $premissaAtual->forceFill([
            'id' => 10,
            'nome' => 'Premissas CEF',
            'perfil_financiamento' => PerfilFinanciamento::CEF->value,
            'ativo' => true,
            'versao' => 3,
            'vigente_em' => '2026-06-01',
            'encerrada_em' => null,
            'compra_terreno' => 1000000,
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $novaVersao = new PremissasViabilidade;
        $novaVersao->forceFill([
            'id' => 11,
            'versao' => 4,
            'compra_terreno' => 1250000,
        ]);

        $repository = $this->createMock(PremissasViabilidadeRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('nextVersion')
            ->with(PerfilFinanciamento::CEF->value)
            ->willReturn(4);
        $repository->expects($this->once())
            ->method('closeCurrentVersion')
            ->with($premissaAtual, '2026-06-29');
        $repository->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $payload): bool {
                return $payload['versao'] === 4
                    && $payload['vigente_em'] === '2026-06-30'
                    && $payload['compra_terreno'] === 1250000
                    && $payload['created_by'] === 9
                    && $payload['updated_by'] === 9
                    && $payload['ativo'] === true;
            }))
            ->willReturn($novaVersao);

        $service = new PremissasViabilidadeCrudService($repository);

        $result = $service->update($premissaAtual, [
            'compra_terreno' => 1250000,
            'vigente_em' => '2026-06-30',
            'created_by' => 9,
            'updated_by' => 9,
        ]);

        $this->assertSame($novaVersao, $result);

        Carbon::setTestNow();
    }
}
