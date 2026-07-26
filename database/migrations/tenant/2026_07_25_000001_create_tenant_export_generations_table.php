<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_export_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->string('type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('filters')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('storage_disk')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['requested_by', 'idempotency_key']);
            $table->index(['requested_by', 'status']);
            $table->index(['status', 'updated_at']);
            $table->index(['type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_export_generations');
    }
};
