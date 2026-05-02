<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premissas_viabilidade', function (Blueprint $table) {
            $table->decimal('bonus_equipe_comercial', 12, 2)->default(0.00)->after('bonus_gestor_comercial');
        });

        Schema::table('viabilidades', function (Blueprint $table) {
            $table->decimal('bonus_equipe_comercial', 12, 2)->nullable()->after('bonus_gestor_comercial');
        });
    }

    public function down(): void
    {
        Schema::table('premissas_viabilidade', function (Blueprint $table) {
            $table->dropColumn('bonus_equipe_comercial');
        });

        Schema::table('viabilidades', function (Blueprint $table) {
            $table->dropColumn('bonus_equipe_comercial');
        });
    }
};

