<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terrenos', function (Blueprint $table) {
            $table->string('municipio_ibge_codigo', 10)->nullable()->after('steep_polygons');
            $table->string('municipio_nome')->nullable()->after('municipio_ibge_codigo');
            $table->string('estado_sigla', 2)->nullable()->after('municipio_nome');
            $table->string('estado_nome')->nullable()->after('estado_sigla');
            $table->string('regiao_nome')->nullable()->after('estado_nome');
            $table->string('mesorregiao_nome')->nullable()->after('regiao_nome');
            $table->string('microrregiao_nome')->nullable()->after('mesorregiao_nome');
        });
    }

    public function down(): void
    {
        Schema::table('terrenos', function (Blueprint $table) {
            $table->dropColumn([
                'municipio_ibge_codigo',
                'municipio_nome',
                'estado_sigla',
                'estado_nome',
                'regiao_nome',
                'mesorregiao_nome',
                'microrregiao_nome',
            ]);
        });
    }
};
