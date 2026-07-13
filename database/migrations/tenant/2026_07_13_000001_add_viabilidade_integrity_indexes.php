<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unique version per terreno (impede corrida de versionamento).
        Schema::table('viabilidades', function (Blueprint $table): void {
            $table->unique(['terreno_id', 'version'], 'viabilidades_terreno_version_unique');
        });

        // Índices parciais: no máximo uma is_current e uma aprovada por terreno.
        // SQLite e PostgreSQL suportam índices parciais via SQL cru.
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS viabilidades_terreno_is_current_unique ON viabilidades (terreno_id) WHERE is_current = true AND deleted_at IS NULL');
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS viabilidades_terreno_aprovada_unique ON viabilidades (terreno_id) WHERE approval_status = 'aprovada' AND deleted_at IS NULL");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS viabilidades_terreno_is_current_unique');
            DB::statement('DROP INDEX IF EXISTS viabilidades_terreno_aprovada_unique');
        }

        Schema::table('viabilidades', function (Blueprint $table): void {
            $table->dropUnique('viabilidades_terreno_version_unique');
        });
    }
};
