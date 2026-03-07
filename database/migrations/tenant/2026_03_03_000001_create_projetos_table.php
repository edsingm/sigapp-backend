<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projetos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->foreignId('terreno_id')->constrained('terrenos');
            $table->foreignId('responsavel_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('em_viabilidade');
            $table->timestamp('pronto_para_registro_em')->nullable();
            $table->foreignId('pronto_para_registro_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['terreno_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projetos');
    }
};
