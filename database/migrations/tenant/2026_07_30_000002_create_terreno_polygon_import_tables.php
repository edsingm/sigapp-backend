<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terreno_polygon_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->string('status')->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('processed_files')->default(0);
            $table->unsignedInteger('failed_files')->default(0);
            $table->unsignedInteger('polygon_count')->default(0);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['requested_by', 'idempotency_key']);
            $table->index(['requested_by', 'status']);
        });

        Schema::create('terreno_polygon_import_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('terreno_polygon_import_id')->constrained('terreno_polygon_imports')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('storage_disk')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('status')->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['terreno_polygon_import_id', 'status']);
        });

        Schema::create('terreno_pending_polygons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('terreno_polygon_import_id')->constrained('terreno_polygon_imports')->cascadeOnDelete();
            $table->foreignId('terreno_polygon_import_file_id')->constrained('terreno_polygon_import_files')->cascadeOnDelete();
            $table->string('source_entry')->nullable();
            $table->string('placemark_name')->nullable();
            $table->unsignedInteger('geometry_index')->default(0);
            $table->json('polygon_coords');
            $table->string('geometry_hash', 64)->unique();
            $table->decimal('min_lat', 11, 8);
            $table->decimal('max_lat', 11, 8);
            $table->decimal('min_lng', 12, 8);
            $table->decimal('max_lng', 12, 8);
            $table->string('status')->default('pending');
            $table->foreignId('terreno_id')->nullable()->constrained('terrenos')->nullOnDelete();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'min_lng', 'max_lng']);
            $table->index(['status', 'min_lat', 'max_lat']);
            $table->index(['terreno_polygon_import_id', 'status'], 'terreno_polygon_import_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terreno_pending_polygons');
        Schema::dropIfExists('terreno_polygon_import_files');
        Schema::dropIfExists('terreno_polygon_imports');
    }
};
