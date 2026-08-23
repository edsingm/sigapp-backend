<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corretores_externos', function (Blueprint $table): void {
            $table->dropUnique('corretores_externos_email_unique');
            $table->text('email')->change();
            $table->text('telefone')->change();
            $table->string('email_hash', 64)->nullable();
            $table->string('telefone_hash', 64)->nullable();
            $table->unique('email_hash');
            $table->index('telefone_hash');
        });

        Schema::table('terreno_proprietarios', function (Blueprint $table): void {
            foreach (['rg', 'cpf_cnpj', 'email', 'telefone', 'endereco', 'cep', 'conjuge_rg', 'conjuge_cpf_cnpj'] as $column) {
                $table->text($column)->nullable()->change();
            }
        });

        Schema::table('terreno_contatos', function (Blueprint $table): void {
            $table->text('telefone')->nullable()->change();
            $table->text('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('corretores_externos', function (Blueprint $table): void {
            $table->dropUnique(['email_hash']);
            $table->dropIndex(['telefone_hash']);
            $table->dropColumn(['email_hash', 'telefone_hash']);
            $table->unique('email');
        });

        // As colunas permanecem TEXT para não truncar ciphertext durante rollback.
    }
};
