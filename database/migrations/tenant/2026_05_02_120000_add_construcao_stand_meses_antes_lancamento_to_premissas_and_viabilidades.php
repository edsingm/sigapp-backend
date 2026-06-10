<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premissas_viabilidade', function (Blueprint $table) {
            $table->unsignedInteger('construcao_stand_meses_antes_lancamento')->default(4)->after('mobilia_decoracao');
        });

        Schema::table('viabilidades', function (Blueprint $table) {
            $table->unsignedInteger('construcao_stand_meses_antes_lancamento')->nullable()->after('mobilia_decoracao');
        });
    }

    public function down(): void
    {
        Schema::table('premissas_viabilidade', function (Blueprint $table) {
            $table->dropColumn('construcao_stand_meses_antes_lancamento');
        });

        Schema::table('viabilidades', function (Blueprint $table) {
            $table->dropColumn('construcao_stand_meses_antes_lancamento');
        });
    }
};
