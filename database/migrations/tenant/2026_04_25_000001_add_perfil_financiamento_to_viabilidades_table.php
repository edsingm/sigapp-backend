<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viabilidades', function (Blueprint $table) {
            $table->string('perfil_financiamento')->default('cef')->after('taxa_exposicao_aplicada');
        });
    }

    public function down(): void
    {
        Schema::table('viabilidades', function (Blueprint $table) {
            $table->dropColumn('perfil_financiamento');
        });
    }
};
