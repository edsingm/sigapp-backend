<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legalizacao_etapas', function (Blueprint $table) {
            if (!Schema::hasColumn('legalizacao_etapas', 'custos')) {
                $table->json('custos')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('legalizacao_etapas', function (Blueprint $table) {
            if (Schema::hasColumn('legalizacao_etapas', 'custos')) {
                $table->dropColumn('custos');
            }
        });
    }
};
