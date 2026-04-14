<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Migrations\AiMigration;

return new class extends AiMigration
{
    public function up(): void
    {
        Schema::create('ai_document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('terreno_documentos')->cascadeOnDelete();
            $table->foreignId('terreno_id')->nullable()->constrained('terrenos')->nullOnDelete();
            $table->integer('chunk_index')->default(0);
            $table->text('content');
            $table->json('metadata')->nullable()->comment('Tipo documento, pagina, etc.');
            $table->timestamps();

            $table->index(['terreno_id', 'document_id']);
            $table->index(['terreno_id']);
            $table->index(['document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_document_chunks');
    }
};
