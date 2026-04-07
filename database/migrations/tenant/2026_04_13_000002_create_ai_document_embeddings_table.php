<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Migrations\AiMigration;

return new class extends AiMigration
{
    public function up(): void
    {
        Schema::create('ai_document_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chunk_id')->constrained('ai_document_chunks')->cascadeOnDelete();
            $table->json('embedding')->comment('Vetor de floats gerado pelo provider');
            $table->string('provider', 50)->default('openai');
            $table->string('model', 100)->default('text-embedding-3-small');
            $table->integer('dimensions')->default(1536)->comment('Dimensões do vetor');
            $table->timestamps();

            $table->index('chunk_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_document_embeddings');
    }
};
