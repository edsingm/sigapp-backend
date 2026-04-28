<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premissas_viabilidade', function (Blueprint $table) {
            $table->integer('versao')->default(1)->after('ativo')
                ->comment('Número da versão das premissas (autoincremental por perfil)');

            $table->date('vigente_em')->nullable()->after('versao')
                ->comment('Data a partir da qual esta versão entra em vigor');

            $table->date('encerrada_em')->nullable()->after('vigente_em')
                ->comment('Data de encerramento desta versão (NULL = vigente até ser substituída)');
        });

        Schema::table('viabilidades', function (Blueprint $table) {
            $table->json('premissas_snapshot')->nullable()
                ->comment('Snapshot dos parâmetros utilizados no último cálculo');
        });
    }

    public function down(): void
    {
        Schema::table('premissas_viabilidade', function (Blueprint $table) {
            $table->dropColumn(['versao', 'vigente_em', 'encerrada_em']);
        });

        Schema::table('viabilidades', function (Blueprint $table) {
            $table->dropColumn('premissas_snapshot');
        });
    }
};
