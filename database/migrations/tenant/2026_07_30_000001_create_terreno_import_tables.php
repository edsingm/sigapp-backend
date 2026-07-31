<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terreno_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->string('status')->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('storage_disk')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['requested_by', 'idempotency_key']);
            $table->index(['requested_by', 'status']);
            $table->index(['status', 'updated_at']);
        });

        Schema::create('terreno_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('terreno_import_id')->constrained('terreno_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->json('normalized_data')->nullable();
            $table->string('status');
            $table->json('errors')->nullable();
            $table->foreignId('terreno_id')->nullable()->constrained('terrenos')->nullOnDelete();
            $table->timestamps();

            $table->unique(['terreno_import_id', 'row_number']);
            $table->index(['terreno_import_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terreno_import_rows');
        Schema::dropIfExists('terreno_imports');
    }
};
