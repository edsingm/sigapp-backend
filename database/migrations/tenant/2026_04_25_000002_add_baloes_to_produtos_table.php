<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->json('baloes_anuais')->nullable()->after('curva_obra');
            $table->string('balao_entrega_modo')->default('saldo_restante')->after('baloes_anuais');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn(['baloes_anuais', 'balao_entrega_modo']);
        });
    }
};
