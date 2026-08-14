<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_dpo')->default(false)->after('is_admin');
        });

        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('protocol', 32)->unique();
            $table->string('kind', 32);
            $table->string('subject_type', 32);
            $table->string('subject_email');
            $table->string('tenant_id')->nullable();
            $table->string('status', 32);
            $table->text('legal_hold_reason')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('due_at');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('export_path')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index(['status', 'due_at']);
            $table->index('subject_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_dpo');
        });
    }
};
