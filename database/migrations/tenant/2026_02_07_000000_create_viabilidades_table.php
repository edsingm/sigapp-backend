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
        Schema::create('viabilidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->constrained('terrenos')->cascadeOnDelete();

            $table->decimal('parceria_vgv', 12, 2)->nullable();
            $table->decimal('compra_terreno', 12, 2)->nullable();
            $table->decimal('infra_nao_incidente', 12, 2)->nullable();
            $table->decimal('porcentagem_lote_proprietario', 12, 2)->nullable();
            $table->integer('prazo_obra')->nullable();
            $table->decimal('pis_cofins', 12, 2)->nullable();
            $table->decimal('iss', 12, 2)->nullable();
            $table->decimal('outros_impostos', 12, 2)->nullable();
            $table->decimal('comissao', 12, 2)->nullable();
            $table->decimal('incorporacao', 12, 2)->nullable();
            $table->decimal('area_comum', 12, 2)->nullable();
            $table->decimal('contrapartidas', 12, 2)->nullable();
            $table->decimal('canteiro_mensal', 12, 2)->nullable();
            $table->decimal('mo_administrativa', 12, 2)->nullable();
            $table->decimal('seguros', 12, 2)->nullable();
            $table->decimal('assistencia_tecnica', 12, 2)->nullable();
            $table->decimal('despesas_comerciais', 12, 2)->nullable();
            $table->decimal('marketing', 12, 2)->nullable();
            $table->decimal('itbi_iptu', 12, 2)->nullable();
            $table->decimal('registro', 12, 2)->nullable();
            $table->decimal('medicao_contratacao', 12, 2)->nullable();
            $table->decimal('contratos_cef', 12, 2)->nullable();
            $table->decimal('produtos_cef', 12, 2)->nullable();
            $table->decimal('outras_despesas_financeiras', 12, 2)->nullable();
            $table->decimal('despesas_onerosas_bancos', 12, 2)->nullable();

            $table->json('resultados_dre')->nullable();
            $table->string('status')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('terreno_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viabilidades');
    }
};
