<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\Documento;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoDependencia;
use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\Proprietario;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Services\Tenant\TenantCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private TenantCacheService $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
        $this->cache = app(TenantCacheService::class);
    }

    public function test_terrain_mutation_invalidates_terrain_and_dashboard_without_flushing_other_modules(): void
    {
        $terrainKey = $this->warm('terrenos');
        $dashboardKey = $this->warm('dashboard');
        $documentsKey = $this->warm('documentos');

        Terreno::query()->create(['nome' => 'Área nova']);

        $this->assertRecomputed('terrenos', $terrainKey);
        $this->assertRecomputed('dashboard', $dashboardKey);
        $this->assertStillWarm('documentos', $documentsKey);
    }

    public function test_models_that_feed_cached_lists_invalidate_their_derived_modules(): void
    {
        $terreno = Terreno::query()->create(['nome' => 'Área']);

        $this->assertMutationInvalidates('corretores_externos', fn () => CorretorExterno::query()->create([
            'nome' => 'Corretor',
            'email' => 'corretor@example.test',
            'telefone' => '11999999999',
            'creci' => 1234,
        ]));

        $this->assertMutationInvalidates('proprietarios', fn () => Proprietario::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => 'Proprietário',
            'tipo_pessoa' => Proprietario::TIPO_FISICA,
        ]));

        $this->assertMutationInvalidates('documentos', fn () => Documento::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => 'Matrícula',
            'tipo' => 'matricula',
            'status' => 'pendente',
        ]));

        $this->assertMutationInvalidates('terrenos', fn () => TerrenoProduto::query()->create([
            'terreno_id' => $terreno->id,
            'unidades' => 10,
            'valor' => 100000,
        ]));
    }

    public function test_legalization_children_invalidate_cached_legalization_details(): void
    {
        $terreno = Terreno::query()->create(['nome' => 'Área']);
        $legalizacao = Legalizacao::query()->create([
            'terreno_id' => $terreno->id,
            'nome' => 'Legalização',
        ]);

        $this->assertMutationInvalidates('legalizacoes', fn () => LegalizacaoEtapa::query()->create([
            'legalizacao_id' => $legalizacao->id,
            'titulo' => 'Etapa 1',
            'inicio_planejado' => now()->toDateString(),
            'fim_planejado' => now()->addDay()->toDateString(),
        ]));

        $origin = LegalizacaoEtapa::query()->create([
            'legalizacao_id' => $legalizacao->id,
            'titulo' => 'Origem',
            'inicio_planejado' => now()->toDateString(),
            'fim_planejado' => now()->addDay()->toDateString(),
        ]);
        $destination = LegalizacaoEtapa::query()->create([
            'legalizacao_id' => $legalizacao->id,
            'titulo' => 'Destino',
            'inicio_planejado' => now()->toDateString(),
            'fim_planejado' => now()->addDay()->toDateString(),
        ]);

        $this->assertMutationInvalidates('legalizacoes', fn () => LegalizacaoDependencia::query()->create([
            'legalizacao_id' => $legalizacao->id,
            'etapa_origem_id' => $origin->id,
            'etapa_destino_id' => $destination->id,
            'tipo' => 'FS',
        ]));
    }

    private function assertMutationInvalidates(string $module, callable $mutation): void
    {
        $key = $this->warm($module);
        $unrelatedKey = $this->warm('unrelated');

        $mutation();

        $this->assertRecomputed($module, $key);
        $this->assertStillWarm('unrelated', $unrelatedKey);
    }

    private function warm(string $module): string
    {
        $key = $this->cache->key($module, 'regression', ['nonce' => uniqid('', true)]);
        $this->cache->remember($module, $key, 300, fn (): string => 'warm');

        return $key;
    }

    private function assertRecomputed(string $module, string $key): void
    {
        $this->assertSame(
            'recomputed',
            $this->cache->remember($module, $key, 300, fn (): string => 'recomputed'),
        );
    }

    private function assertStillWarm(string $module, string $key): void
    {
        $this->assertSame(
            'warm',
            $this->cache->remember($module, $key, 300, fn (): string => 'unexpected'),
        );
    }
}
