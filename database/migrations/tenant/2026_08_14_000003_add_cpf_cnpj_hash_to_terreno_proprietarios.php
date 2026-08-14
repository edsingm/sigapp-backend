<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terreno_proprietarios', function (Blueprint $table): void {
            $table->string('cpf_cnpj_hash', 64)->nullable();
            $table->index('cpf_cnpj_hash');
        });
    }

    public function down(): void
    {
        Schema::table('terreno_proprietarios', function (Blueprint $table): void {
            $table->dropIndex(['cpf_cnpj_hash']);
            $table->dropColumn('cpf_cnpj_hash');
        });
    }
};
