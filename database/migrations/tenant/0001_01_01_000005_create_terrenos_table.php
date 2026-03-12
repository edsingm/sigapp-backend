<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('terrenos', function (Blueprint $table) {
            $table->id();

            $table->string('nome');

            $table->foreignId('responsavel_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('endereco')->nullable();

            $table->foreignId('corretor_id')->nullable();

            $table->string('estado', 2)->nullable();
            $table->string('cidade_code')->nullable();
            // $table->foreign('cidade_code')->references('code')->on('cidades')->nullOnDelete(); // FK removida pois tabela 'cidades' é central

            $table->json('polygon_coords')->nullable();
            $table->string('static_map_url')->nullable();

            $table->decimal('area_calculada', 12, 2)->nullable();

            $table->foreignId('regional_id')->nullable();

            $table->string('cep', 10)->nullable();
            $table->string('bairro')->nullable();
            $table->text('observacoes')->nullable();

            $table->decimal('valor', 12, 2)->nullable();
            $table->string('zona')->nullable();
            $table->string('distrito')->nullable();
            $table->string('operacao_urbana')->nullable();

            $table->date('data_apresentacao')->nullable();
            $table->date('data_negociacao')->nullable();
            $table->date('data_opcao')->nullable();
            $table->date('data_descarte')->nullable();
            $table->date('data_contrato')->nullable();

            $table->foreignId('comprador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['regional_id']);
            $table->index(['corretor_id']);
            $table->index(['responsavel_id']);
            $table->index(['comprador_id']);
            $table->index(['estado']);
            $table->index(['cidade_code']);
            $table->index(['created_at']);
            $table->index(['data_apresentacao']);
            $table->index(['data_negociacao']);
            $table->index(['data_contrato']);
            $table->index(['data_opcao']);
            $table->index(['data_descarte']);
            $table->index(['nome']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terrenos');
    }
};
