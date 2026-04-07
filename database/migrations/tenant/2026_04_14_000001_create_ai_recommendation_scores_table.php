<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Migrations\AiMigration;

return new class extends AiMigration
{
    public function up(): void
    {
        Schema::create('ai_recommendation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->constrained('terrenos')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0)->comment('Score 0.00 a 100.00');
            $table->string('tier', 20)->default('sem_classificacao')
                ->comment('alta_prioridade, media, atencao, baixa, sem_classificacao');
            $table->json('factors')->nullable()->comment('Variáveis que influenciaram o score');
            $table->integer('version')->default(1)->comment('Versão do algoritmo de scoring');
            $table->timestamps();

            $table->index(['terreno_id', 'score']);
            $table->index(['tier']);
            $table->index(['terreno_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_recommendation_scores');
    }
};
