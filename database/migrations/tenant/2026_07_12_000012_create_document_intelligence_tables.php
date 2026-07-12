<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requirements', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 60);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('phase', 60)->default('prospeccao');
            $table->string('document_type', 80);
            $table->string('label');
            $table->boolean('required')->default(true);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id', 'phase', 'document_type']);
            $table->index(['entity_type', 'entity_id', 'active']);
        });

        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('documento_id')->constrained('terreno_documentos')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_path');
            $table->string('disk')->default('s3');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['documento_id', 'version']);
            $table->unique(['documento_id', 'checksum']);
        });

        Schema::create('document_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('documento_id')->constrained('terreno_documentos')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('queued');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('extracted_fields')->nullable();
            $table->json('limitations')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['documento_id', 'status']);
        });

        Schema::create('document_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('documento_id')->constrained('terreno_documentos')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['documento_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_reviews');
        Schema::dropIfExists('document_analyses');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('document_requirements');
    }
};
