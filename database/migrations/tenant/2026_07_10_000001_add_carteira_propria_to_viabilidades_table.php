<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('viabilidades', function (Blueprint $table): void {
            $table->string('tipo_empreendimento')->default('incorporacao')->after('perfil_financiamento');
            $table->string('estrategia_financeira')->default('cef')->after('tipo_empreendimento');
            $table->json('carteira_propria_parametros')->nullable()->after('estrategia_financeira');
        });
    }

    public function down(): void
    {
        Schema::table('viabilidades', function (Blueprint $table): void {
            $table->dropColumn(['tipo_empreendimento', 'estrategia_financeira', 'carteira_propria_parametros']);
        });
    }
};
