<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('terreno_proprietarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->constrained('terrenos')->onDelete('cascade');
            $table->string('nome');
            $table->string('rg')->nullable();
            $table->string('cpf_cnpj')->nullable();
            $table->date('nascimento')->nullable();
            $table->string('tipo_pessoa')->default('fisica'); // fisica or juridica
            $table->string('estado_civil')->nullable();
            $table->string('nacionalidade')->nullable();
            $table->string('profissao')->nullable();
            $table->decimal('porcentagem_terreno', 5, 2)->nullable();
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            $table->string('endereco')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado')->nullable();
            $table->string('cep')->nullable();

            // Spouse Info
            $table->string('conjuge')->nullable();
            $table->string('conjuge_rg')->nullable();
            $table->date('conjuge_nascimento')->nullable();
            $table->string('conjuge_cpf_cnpj')->nullable();

            $table->text('observacoes')->nullable();

            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terreno_proprietarios');
    }
};
