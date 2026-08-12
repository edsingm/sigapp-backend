<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hiperdados_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status', 32)->default('queued')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('portal_username', 255);
            $table->text('credentials_encrypted')->nullable();
            $table->string('tenant_id')->nullable()->index();
            $table->unsignedInteger('limit_count')->nullable();
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->string('storage_disk', 64)->nullable();
            $table->string('storage_path', 512)->nullable();
            $table->text('error_message')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hiperdados_imports');
    }
};
