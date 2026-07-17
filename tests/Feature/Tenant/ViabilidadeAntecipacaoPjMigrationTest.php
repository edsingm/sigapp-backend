<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ViabilidadeAntecipacaoPjMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_preserva_comportamento_dos_estudos_existentes(): void
    {
        Schema::create('viabilidades', function (Blueprint $table): void {
            $table->id();
            $table->decimal('percentual_antecipacao_pj', 8, 4)->nullable();
        });

        DB::table('viabilidades')->insert([
            ['id' => 1, 'percentual_antecipacao_pj' => null],
            ['id' => 2, 'percentual_antecipacao_pj' => 0],
            ['id' => 3, 'percentual_antecipacao_pj' => 10],
        ]);

        $migration = require database_path('migrations/tenant/2026_07_17_000000_add_usar_antecipacao_pj_to_viabilidades.php');
        if (! is_object($migration)) {
            $this->fail('A migration de antecipação PJ não pôde ser carregada.');
        }

        $up = [$migration, 'up'];
        if (! is_callable($up)) {
            $this->fail('A migration de antecipação PJ não possui método up.');
        }
        $up();

        $this->assertDatabaseHas('viabilidades', ['id' => 1, 'usar_antecipacao_pj' => true]);
        $this->assertDatabaseHas('viabilidades', ['id' => 2, 'usar_antecipacao_pj' => false]);
        $this->assertDatabaseHas('viabilidades', ['id' => 3, 'usar_antecipacao_pj' => true]);

        $down = [$migration, 'down'];
        if (! is_callable($down)) {
            $this->fail('A migration de antecipação PJ não possui método down.');
        }
        $down();

        $this->assertFalse(Schema::hasColumn('viabilidades', 'usar_antecipacao_pj'));
    }
}
