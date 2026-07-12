<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_captures', function (Blueprint $table): void {
            $table->id();
            $table->uuid('client_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('draft');
            $table->json('payload')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->foreignId('terreno_id')->nullable()->constrained('terrenos')->nullOnDelete();
            $table->json('conflict_details')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'client_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('mobile_capture_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mobile_capture_id')->constrained('mobile_captures')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('disk')->default('s3');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('status')->default('uploaded');
            $table->timestamps();

            $table->index(['mobile_capture_id', 'status']);
            $table->unique(['mobile_capture_id', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_capture_attachments');
        Schema::dropIfExists('mobile_captures');
    }
};
